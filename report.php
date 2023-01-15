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

admin_externalpage_setup('mod_syllabus');

$url = new moodle_url('/mod/syllabus/report.php');
// $PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(get_string('reports'));
$PAGE->set_heading(get_string('reports'));

// $PAGE->requires->js_call_amd('mod/syllabus/manage', 'init');
// $PAGE->requires->css('mod/syllabus/styles/select2.css');

$output = $PAGE->get_renderer('mod_syllabus');

echo $output->header();
echo $output->heading(get_string('reports'));

$syllabus = $DB->get_records_sql('SELECT 
                                            c.id, cc.name AS category, c.fullname AS course
                                        FROM
                                            {course} c,
                                            {course_categories} cc
                                        WHERE
                                            c.category = cc.id
                                                AND c.id NOT IN (SELECT 
                                                    course
                                                FROM
                                                    {syllabus}
                                                GROUP BY id)');

$table = new html_table();
$table->head = array();
$table->colclasses = array();
$table->head[] = 'Course Name';
$table->head[] = 'Course ID';
$table->head[] = 'Category';
$table->id = 'syllabusreport';


foreach ($syllabus as $item) {
    $row = array();
    $row[] = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $item->id . '">' . $item->course . '</a>';
    $row[] = $item->id;
    $row[] = $item->category;
    $table->data[] = $row;
}

echo html_writer::start_tag('div', array('class' => 'no-overflow'));
echo html_writer::table($table);
echo html_writer::end_tag('div');

//$renderable = new \mod_syllabus\output\report_page($syllabus);

// echo $output->render($renderable);
echo $output->footer();