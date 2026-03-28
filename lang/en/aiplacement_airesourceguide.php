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
 * Language strings for the AI Resource Guide placement.
 *
 * @package    aiplacement_airesourceguide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ai_disclaimer'] = 'References are AI-generated suggestions. Always verify sources for accuracy.';
$string['aicallfailed'] = 'Failed to communicate with the AI service. Please try again later.';
$string['aipolicy_message'] = 'This feature uses AI to analyze course content and suggest learning references. By proceeding, you acknowledge the following:';
$string['aipolicy_point1'] = 'Course content will be sent to an AI service for analysis.';
$string['aipolicy_point2'] = 'AI-generated suggestions should be verified for accuracy.';
$string['aipolicy_point3'] = 'Your usage will be logged in accordance with the site\'s privacy policy.';
$string['aipolicy_title'] = 'AI Usage Policy';
$string['airesourceguide:viewreferences'] = 'View AI-generated learning references';
$string['drawer_description'] = 'AI-generated learning references based on this page\'s content. Click any link to explore further.';
$string['emptyairesponse'] = 'The AI service returned an empty response. Please try again.';
$string['emptypage'] = 'This page has no content to analyze.';
$string['enabled_sources'] = 'Enabled reference sources';
$string['enabled_sources_desc'] = 'Select which types of reference sources should be available to students. AI will suggest the most relevant sources from this list based on the content subject.';
$string['error_generating'] = 'Something went wrong while generating references. Please try again later.';
$string['findreferences'] = 'Find References';
$string['generating'] = 'Analyzing content and finding references...';
$string['invalidairesponse'] = 'The AI response could not be parsed. Please try again.';
$string['max_content_length'] = 'Maximum content length';
$string['max_content_length_desc'] = 'Maximum number of characters of page content to send to the AI for analysis. Higher values may improve results for long pages but will increase API usage and cost.';
$string['noconceptsfound'] = 'No key concepts could be identified in this content.';
$string['noresults'] = 'No references could be generated for this content. Try a page with more detailed content.';
$string['pluginname'] = 'AI Resource Guide';
$string['privacy:metadata:null'] = 'This plugin stores AI-generated references in an application-level cache keyed by course module. No personal data is stored.';
$string['source_academic'] = 'Academic Resources (Google Scholar)';
$string['source_books'] = 'Books (Google Books)';
$string['source_documentation'] = 'Technical Documentation (DevDocs)';
$string['source_encyclopedia'] = 'Encyclopedia (Wikipedia)';
$string['source_news'] = 'News & Articles (Google News)';
$string['source_video'] = 'Video Tutorials (YouTube)';
