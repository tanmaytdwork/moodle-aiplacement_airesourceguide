<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Utility class for the AI Resource Guide placement.
 *
 * Handles AI calls, response parsing, caching, and
 * building reference links for Page activity content.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiplacement_airesourceguide;

defined('MOODLE_INTERNAL') || die();

/**
 * Utility methods for retrieving, caching, and building AI-generated references.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
    ];

    /**
     * Get AI-generated references for a Page activity.
     *
     * Returns cached results if available, otherwise calls the AI subsystem.
     *
     * @param int $cmid Course module ID.
     * @return array Array of reference concepts with sources.
     */
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

    /**
     * Retrieve the plain text content of a Page activity.
     *
     * @param int $cmid Course module ID.
     * @return string Plain text content with HTML stripped.
     */
    private static function get_page_content(int $cmid): string {
        global $DB;

        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'page');
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        return trim(strip_tags($page->content));
    }

    /**
     * Call the AI subsystem to extract key concepts from page content.
     *
     * @param string $content Plain text page content.
     * @param int $cmid Course module ID used for context.
     * @return array Array of parsed concept data.
     */
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

    /**
     * Build the AI prompt from page content.
     *
     * Truncates content to the configured max length before sending.
     *
     * @param string $content Plain text page content.
     * @return string The prompt string to send to the AI.
     */
    private static function build_prompt(string $content): string {
        $maxlength = (int) get_config('aiplacement_airesourceguide', 'max_content_length') ?: 4000;
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

    /**
     * Parse the AI JSON response into a structured concepts array.
     *
     * @param string $responsetext Raw text response from the AI.
     * @return array Array of cleaned concept data.
     */
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

    /**
     * Build the final references array from concepts using enabled sources.
     *
     * @param array $concepts Array of concept data from parse_response().
     * @return array Array of references ready for template rendering.
     */
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

    /**
     * Get the list of source types enabled in admin settings.
     *
     * @return array Array of enabled source type keys.
     */
    private static function get_enabled_sources(): array {
        $config = get_config('aiplacement_airesourceguide', 'enabled_sources');

        if (empty($config)) {
            return ['academic', 'video', 'encyclopedia', 'documentation'];
        }

        return explode(',', $config);
    }

    /**
     * Retrieve cached references for a course module.
     *
     * @param int $cmid Course module ID.
     * @return array|null Cached references or null if not cached.
     */
    private static function get_cached_references(int $cmid): ?array {
        $cache = \cache::make('aiplacement_airesourceguide', 'references');
        $data = $cache->get($cmid);
        return is_array($data) ? $data : null;
    }

    /**
     * Store references in the application cache.
     *
     * @param int $cmid Course module ID.
     * @param array $references References to cache.
     * @return void
     */
    private static function cache_references(int $cmid, array $references): void {
        $cache = \cache::make('aiplacement_airesourceguide', 'references');
        $cache->set($cmid, $references);
    }
}
