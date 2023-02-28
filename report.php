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
$PAGE->set_title(get_string('reports', 'mod_syllabus'));
$PAGE->set_heading(get_string('reports', 'mod_syllabus'));

$output = $PAGE->get_renderer('mod_syllabus');

echo $output->header();
echo $output->heading(get_string('reports', 'mod_syllabus'));

$renderable = new \mod_syllabus\output\report_page();

$data = new stdClass();
$data->tmp = $DB->get_records_sql('SELECT 
                                            c.id, c.name, COUNT(DISTINCT cc.id) AS count
                                        FROM
                                            {course_categories} AS c
                                                LEFT JOIN
                                            {course_categories} AS c2 ON c.id = c2.parent
                                                LEFT JOIN
                                            {course} AS cc ON (cc.category = c.id
                                                OR cc.category = c2.id)
                                        WHERE c.id IN (' . implode(',', $renderable->relcategories()) . ')                                                                        
                                        GROUP BY c.id, c.name');
$data->courses = array();
$data->labels = array();
$data->completed = array();
$data->empty = array();

foreach ($data->tmp as $tmp) {

    $completed = $DB->get_records_sql('SELECT s.id
                                            FROM
                                                {syllabus} s,
                                                {course} c,
                                                {course_categories} cc
                                            WHERE
                                                s.course = c.id AND cc.id = ?
                                                AND c.category = cc.id
                                            GROUP BY s.course, s.id', array($tmp->id));

    array_push($data->courses, $tmp->count);
    array_push($data->labels, $renderable->breadcrumb($tmp->id));
    array_push($data->completed, count($completed));
    array_push($data->empty, ($tmp->count - count($completed)));
}

$courses = new \core\chart_series(get_string('courses'), $data->courses);
$empty = new \core\chart_series(get_string('withoutsyllabus', 'mod_syllabus'), $data->empty);
$completed = new \core\chart_series(get_string('withsyllabus', 'mod_syllabus'), $data->completed);

$chart = new \core\chart_bar();
$chart->set_title(get_string('reports', 'mod_syllabus'));
$chart->set_horizontal(true);
$chart->add_series($courses);
$chart->add_series($completed);
$chart->add_series($empty);
$chart->set_labels($data->labels);

echo $output->render($chart);
echo $output->render($renderable);
echo $output->footer();