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
 * Privacy provider for the AI Resource Guide placement.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiplacement_airesourceguide\privacy;

/**
 * Privacy provider implementation.
 *
 * This plugin uses Moodle's MUC (application-level cache) keyed by course
 * module ID. No personal data is stored — the cache contains only
 * AI-generated reference links for page content, not user-specific data.
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Returns the reason this plugin stores no personal data.
     *
     * @return string Language string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata:null';
    }
}
