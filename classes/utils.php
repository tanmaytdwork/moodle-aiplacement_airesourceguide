<?php

namespace aiplacement_airesourceguide;

defined('MOODLE_INTERNAL') || die();

class utils {

    private const SOURCE_TYPES = [
        'academic' => [
            'label' => 'source_academic',
            'icon'  => 'graduation-cap',
            'url'   => 'https://scholar.google.com/scholar?q={query}',
        ],
        'video' => [
            'label' => 'source_video',
            'icon'  => 'video',
            'url'   => 'https://www.youtube.com/results?search_query={query}',
        ],
        'encyclopedia' => [
            'label' => 'source_encyclopedia',
            'icon'  => 'globe',
            'url'   => 'https://en.wikipedia.org/wiki/Special:Search/{query}',
        ],
        'documentation' => [
            'label' => 'source_documentation',
            'icon'  => 'book',
            'url'   => 'https://devdocs.io/#q={query}',
        ],
        'news' => [
            'label' => 'source_news',
            'icon'  => 'newspaper-o',
            'url'   => 'https://news.google.com/search?q={query}',
        ],
        'books' => [
            'label' => 'source_books',
            'icon'  => 'book',
            'url'   => 'https://www.google.com/search?tbm=bks&q={query}',
        ],
        'interactive' => [
            'label' => 'source_interactive',
            'icon'  => 'play-circle',
            'url'   => 'https://www.khanacademy.org/search?page_search_query={query}',
        ],
    ];

    public static function get_references(int $cmid): array {
        global $USER;

        $content = self::get_page_content($cmid);

        if (empty(trim($content))) {
            return [];
        }
        $cached = self::get_cached_references($cmid);
        if ($cached !== null) {
            return $cached;
        }

        $concepts = self::extract_concepts($content, $cmid);

        $references = self::build_references($concepts);

        self::cache_references($cmid, $references);

        return $references;
    }

    private static function get_page_content(int $cmid): string {
        global $DB;

        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'page');
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        return trim(strip_tags($page->content));
    }

    private static function extract_concepts(string $content, int $cmid): array {
        global $USER;

        $prompt = self::build_prompt($content);
        $contextid = \context_module::instance($cmid)->id;

        $action = new \core_ai\aiactions\generate_text(
            contextid: $contextid,
            userid: $USER->id,
            prompttext: $prompt
        );

        $manager = \core\di::get(\core_ai\manager::class);
        $response = $manager->process_action($action);

        if (!$response->get_success()) {
            $errorcode = $response->get_errorcode() ?? 0;
            $errormessage = $response->get_errormessage() ?? '';
            throw new \moodle_exception(
                'aicallfailed',
                'aiplacement_airesourceguide',
                '',
                null,
                "Error code: {$errorcode}, Message: {$errormessage}"
            );
        }

        $responsedata = $response->get_response_data();
        $generatedtext = $responsedata['generatedcontent'] ?? '';

        if (empty($generatedtext)) {
            return [];
        }

        return self::parse_response($generatedtext);
    }

    private static function build_prompt(string $content): string {
        $maxlength = 4000;
        if (strlen($content) > $maxlength) {
            $content = substr($content, 0, $maxlength) . "\n\n[Content truncated]";
        }

        return "You are an expert educator. Analyze the following learning content " .
               "and identify 3 to 5 key concepts that a student might need " .
               "additional help understanding.\n\n" .
               "For each concept, provide:\n" .
               "- topic: A short name for the concept (max 5 words)\n" .
               "- search_query: Effective search terms a student should use " .
               "to find learning resources about this topic (max 8 words)\n" .
               "- description: A one-sentence explanation of the concept " .
               "(max 25 words)\n" .
               "- suggested_sources: An array of 2 to 4 source types that are " .
               "most relevant for this topic. Choose from: " .
               "academic, video, encyclopedia, documentation, news, books, interactive\n\n" .
               "Choose source types intelligently based on the subject matter. " .
               "For example:\n" .
               "- Programming topics: documentation, video, interactive\n" .
               "- Science topics: academic, video, encyclopedia\n" .
               "- History topics: encyclopedia, books, video\n" .
               "- Business topics: news, academic, books\n" .
               "- Mathematics topics: video, interactive, academic\n\n" .
               "IMPORTANT: Respond ONLY with valid JSON in this exact format, " .
               "no additional text or markdown:\n" .
               "{\"concepts\": [{\"topic\": \"...\", \"search_query\": \"...\", " .
               "\"description\": \"...\", \"suggested_sources\": [\"...\", \"...\"]}]}\n\n" .
               "Content:\n" . $content;
    }

    private static function parse_response(string $responsetext): array {
        $responsetext = trim($responsetext);
        $responsetext = preg_replace('/^```(?:json)?\s*/i', '', $responsetext);
        $responsetext = preg_replace('/\s*```$/', '', $responsetext);

        $data = json_decode($responsetext, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception(
                'invalidairesponse',
                'aiplacement_airesourceguide',
                '',
                null,
                'JSON error: ' . json_last_error_msg()
            );
        }

        if (!isset($data['concepts']) || !is_array($data['concepts'])) {
            throw new \moodle_exception('invalidairesponse', 'aiplacement_airesourceguide');
        }
        $concepts = [];
        foreach ($data['concepts'] as $concept) {
            if (empty($concept['topic']) || empty($concept['search_query'])) {
                continue; 
            }

            $concepts[] = [
                'topic'             => clean_param($concept['topic'], PARAM_TEXT),
                'search_query'      => clean_param($concept['search_query'], PARAM_TEXT),
                'description'       => clean_param($concept['description'] ?? '', PARAM_TEXT),
                'suggested_sources' => $concept['suggested_sources'] ?? ['video', 'encyclopedia'],
            ];
        }

        if (empty($concepts)) {
            return [];
        }

        return $concepts;
    }

    private static function build_references(array $concepts): array {
        $enabledsources = self::get_enabled_sources();

        $references = [];
        foreach ($concepts as $concept) {
            $sources = [];
            $encodedquery = urlencode($concept['search_query']);

            foreach ($concept['suggested_sources'] as $sourcetype) {
                if (in_array($sourcetype, $enabledsources) && isset(self::SOURCE_TYPES[$sourcetype])) {
                    $sourceconfig = self::SOURCE_TYPES[$sourcetype];
                    $sources[] = [
                        'label' => get_string($sourceconfig['label'], 'aiplacement_airesourceguide'),
                        'icon'  => $sourceconfig['icon'],
                        'url'   => str_replace('{query}', $encodedquery, $sourceconfig['url']),
                    ];
                }
            }

            if (!empty($sources)) {
                $references[] = [
                    'topic'       => $concept['topic'],
                    'description' => $concept['description'],
                    'sources'     => $sources,
                ];
            }
        }

        return $references;
    }

    private static function get_enabled_sources(): array {
        $config = get_config('aiplacement_airesourceguide', 'enabled_sources');

        if (empty($config)) {
            return ['academic', 'video', 'encyclopedia', 'documentation', 'interactive'];
        }

        return explode(',', $config);
    }

    private static function get_cached_references(int $cmid): ?array {
        $cache = \cache::make('aiplacement_airesourceguide', 'references');
        $data = $cache->get($cmid);
        return is_array($data) ? $data : null;
    }

    private static function cache_references(int $cmid, array $references): void {
        $cache = \cache::make('aiplacement_airesourceguide', 'references');
        $cache->set($cmid, $references);
    }
}
