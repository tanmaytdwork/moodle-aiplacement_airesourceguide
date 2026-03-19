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
 * Event observer for the AI Resource Guide placement.
 *
 * Listens for Page activity updates and invalidates the
 * cached references so fresh results are generated.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiplacement_airesourceguide;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for invalidating cached references on Page activity updates.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Invalidate cached references when a Page activity is updated.
     *
     * Only acts on Page modules; ignores all other module types.
     *
     * @param \core\event\course_module_updated $event The event instance.
     * @return void
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        $data = $event->get_data();
        $other = $data['other'];

        if ($other['modulename'] !== 'page') {
            return;
        }

        $cmid = $data['objectid'];

        $cache = \cache::make('aiplacement_airesourceguide', 'references');

        if ($cache->get($cmid) !== false) {
            $cache->delete($cmid);
        }
    }
}
