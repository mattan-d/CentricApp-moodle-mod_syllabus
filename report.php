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

opcache_reset();

admin_externalpage_setup('mod_syllabus');

$url = new moodle_url('/mod/syllabus/report.php');
// $PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(get_string('reports', 'mod_syllabus'));
$PAGE->set_heading(get_string('reports', 'mod_syllabus'));

// $PAGE->requires->js_call_amd('mod/syllabus/manage', 'init');
// $PAGE->requires->css('mod/syllabus/styles/select2.css');

$output = $PAGE->get_renderer('mod_syllabus');

echo $output->header();
echo $output->heading(get_string('reports', 'mod_syllabus'));

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


$data = new stdClass();
$data->tmp = $DB->get_records_sql('SELECT 
                                                cc.id, cc.name, COUNT(cc.name) AS count
                                            FROM
                                                {course} c,
                                                {course_categories} cc
                                            WHERE
                                                c.category = cc.id
                                            GROUP BY name');
$data->courses = array();
$data->labels = array();
$data->completed = array();
$data->empty = array();

foreach ($data->tmp as $tmp) {

    $completed = $DB->get_record_sql('SELECT 
                                                cc.id, COUNT(cc.id) AS count
                                            FROM
                                                {syllabus} s,
                                                {course} c,
                                                {course_categories} cc
                                            WHERE
                                                s.course = c.id AND cc.id = ?
                                                AND c.category = cc.id
                                            GROUP BY cc.id', array($tmp->id));

    array_push($data->courses, $tmp->count);
    array_push($data->labels, $tmp->name);
    array_push($data->completed, $completed->count);
    array_push($data->empty, ($tmp->count - $completed->count));
}

$courses = new \core\chart_series('Courses', $data->courses);
$empty = new \core\chart_series('W/O Syllabus', $data->empty);
$completed = new \core\chart_series('With Syllabus', $data->completed);

$chart = new \core\chart_bar();
$chart->set_title(get_string('reports', 'mod_syllabus'));
$chart->add_series($courses);
$chart->add_series($completed);
$chart->add_series($empty);
$chart->set_labels($data->labels);

echo $output->render($chart);
echo html_writer::start_tag('div', array('class' => 'no-overflow'));
echo html_writer::start_tag('h4');
echo get_string('courses_wo', 'mod_syllabus');
echo html_writer::end_tag('h4');
echo html_writer::table($table);
echo html_writer::end_tag('div');

//$renderable = new \mod_syllabus\output\report_page($syllabus);

// echo $output->render($renderable);
echo $output->footer();