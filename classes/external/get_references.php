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
 * External API for the AI Resource Guide placement.
 *
 * Provides the AJAX-callable web service that the frontend
 * uses to request AI-generated references for a Page activity.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiplacement_airesourceguide\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use aiplacement_airesourceguide\utils;

/**
 * Web service to get AI-generated references for a Page activity.
 */
class get_references extends external_api {
    /**
     * Define the parameters accepted by this web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the Page activity'),
        ]);
    }

    /**
     * Get AI-generated references for the specified Page activity.
     *
     * Security flow:
     * 1. Validate parameters.
     * 2. Verify the course module is a Page activity.
     * 3. Validate context.
     * 4. Check AI policy acceptance.
     * 5. Check user capability.
     * 6. Call the utils class to process.
     *
     * @param int $cmid Course module ID.
     * @return array Array containing concepts with reference links.
     */
    public static function execute(int $cmid): array {
        global $USER;

        // Step 1: Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        // Step 2: Verify it's a Page activity and get course data.
        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'page');

        // Step 3: Validate context.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Step 4: Check AI policy acceptance.
        if (!\core_ai\manager::get_user_policy_status($USER->id)) {
            throw new \moodle_exception('aicallfailed', 'aiplacement_airesourceguide');
        }

        // Step 5: Check capability.
        require_capability('aiplacement/airesourceguide:viewreferences', $context);

        // Step 6: Get references.
        $references = utils::get_references($params['cmid']);

        return ['concepts' => $references];
    }

    /**
     * Define the return structure of this web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'concepts' => new external_multiple_structure(
                new external_single_structure([
                    'topic' => new external_value(PARAM_TEXT, 'Concept name'),
                    'description' => new external_value(PARAM_TEXT, 'Brief description of the concept'),
                    'sources' => new external_multiple_structure(
                        new external_single_structure([
                            'label' => new external_value(PARAM_TEXT, 'Source type label'),
                            'icon'  => new external_value(PARAM_TEXT, 'Font Awesome icon name'),
                            'url'   => new external_value(PARAM_URL, 'Search URL for this source'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
