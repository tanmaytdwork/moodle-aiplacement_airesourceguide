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
 * Admin settings for the AI Resource Guide placement.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core_ai\admin\admin_settingspage_provider;

if ($hassiteconfig) {

    $settings = new admin_settingspage_provider(
        'aiplacement_airesourceguide',
        new lang_string('pluginname', 'aiplacement_airesourceguide'),
        'moodle/site:config',
        true,
    );

    $settings->add(new admin_setting_configtext(
        'aiplacement_airesourceguide/max_content_length',
        new lang_string('max_content_length', 'aiplacement_airesourceguide'),
        new lang_string('max_content_length_desc', 'aiplacement_airesourceguide'),
        4000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'aiplacement_airesourceguide/enabled_sources',
        new lang_string('enabled_sources', 'aiplacement_airesourceguide'),
        new lang_string('enabled_sources_desc', 'aiplacement_airesourceguide'),
        [
            'academic'      => 1,
            'video'         => 1,
            'encyclopedia'  => 1,
            'documentation' => 1,
        ],
        [
            'academic'      => new lang_string('source_academic', 'aiplacement_airesourceguide'),
            'video'         => new lang_string('source_video', 'aiplacement_airesourceguide'),
            'encyclopedia'  => new lang_string('source_encyclopedia', 'aiplacement_airesourceguide'),
            'documentation' => new lang_string('source_documentation', 'aiplacement_airesourceguide'),
            'news'          => new lang_string('source_news', 'aiplacement_airesourceguide'),
            'books'         => new lang_string('source_books', 'aiplacement_airesourceguide'),
        ]
    ));
}
