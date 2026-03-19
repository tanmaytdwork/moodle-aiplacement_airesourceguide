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
 * Hook listener for the AI Resource Guide placement.
 *
 * Injects the AMD module into Page activity footer when
 * conditions are met (correct context, capability, AI availability).
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiplacement_airesourceguide;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook listener that injects the AI Resource Guide into Page activity views.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {


    /**
     * Inject the AI Resource Guide AMD module into the page footer.
     *
     * Only fires on Page activity view pages where the user has the
     * required capability and the AI subsystem is available.
     *
     * @param \core\hook\output\before_footer_html_generation $hook The hook instance.
     * @return void
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook,
    ): void {
        global $PAGE;
        if ($PAGE->context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        try {
            $cm = get_coursemodule_from_id('page', $PAGE->context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return;
            }
            if ($PAGE->bodyid !== 'page-mod-page-view') {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        if (!has_capability('aiplacement/airesourceguide:viewreferences', $PAGE->context)) {
            return;
        }

        $manager = \core\di::get(\core_ai\manager::class);
        if (!$manager->is_action_available(\core_ai\aiactions\generate_text::class)) {
            return;
        }

        $PAGE->requires->js_call_amd(
            'aiplacement_airesourceguide/guide',
            'init',
            ['cmid' => (int)$cm->id]
        );
    }
}
