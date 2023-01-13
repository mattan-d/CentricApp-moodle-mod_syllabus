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
 * Syllabus module version information
 *
 * @package    mod_syllabus
 * @copyright  2023 CentricApp  {@link https://centricapp.co}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Standard GPL and phpdocs
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('reportinsights');

opcache_reset();

$syllabus = $DB->get_records('syllabus');

// Set up the page.
$title = get_string('report', 'mod_syllabus');
$pagetitle = $title;
$url = new moodle_url('/mod/syllabus/report.php');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
opcache_reset();

// $PAGE->requires->js_call_amd('mod/syllabus/manage', 'init');
// $PAGE->requires->css('mod/syllabus/styles/select2.css');

$output = $PAGE->get_renderer('mod_syllabus');

echo $output->header();
echo $output->heading($pagetitle);

$renderable = new \mod_syllabus\output\index_page('Some text');

echo $output->render($renderable);
echo $output->footer();