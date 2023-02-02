<?php
// Standard GPL and phpdocs
namespace mod_syllabus\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

class report_page implements renderable, templatable
{

    public function __construct()
    {
    }

    function breadcrumb($category, $data = array())
    {
        global $DB;
        $out = $DB->get_record('course_categories', array('id' => $category));
        array_push($data, $out->name);

        if ($out->parent > 0)
            return $this->breadcrumb($out->parent, $data);

        return implode(' >> ', array_reverse($data));
    }

    function get_links($course)
    {
        global $DB, $CFG;

        $cm = $DB->get_records_sql('SELECT 
                                    cm.id
                                FROM
                                    {course_modules} cm,
                                    {modules} m
                                WHERE
                                    cm.course = ? AND m.id = cm.module
                                        AND m.name = \'syllabus\'', array($course));

        $fs = get_file_storage();
        $out = array();

        foreach ($cm as $c) {
            $context = \context_module::instance($c->id);
            $files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', 0);
            foreach ($files as $file) {
                if ($file->get_filesize() > 0) {
                    $filename = $file->get_filename();
                    $url = \moodle_url::make_file_url($CFG->wwwroot . '/pluginfile.php', '/' . $file->get_contextid() . '/' . '/mod_syllabus/content/' . $file->get_itemid() . '/' . $filename);
                    $out[] = \html_writer::link($url, $filename);
                }
            }
        }

        return implode('<br>', $out);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output)
    {
        global $DB, $CFG;
        $data = new stdClass();

        $syllabus = $DB->get_records_sql('SELECT 
                                    c.id,
                                    cc.id AS category,
                                    c.fullname AS course,
                                    FROM_UNIXTIME(c.timemodified) AS timemodified,
                                    FROM_UNIXTIME(c.startdate) AS startdate,
                                    FROM_UNIXTIME(c.enddate) AS enddate,
                                    (SELECT 
                                            count(id)
                                        FROM
                                            {syllabus}
                                        WHERE
                                            course = c.id
                                        GROUP BY course) as count
                                FROM
                                    {course} c,
                                    {course_categories} cc
                                WHERE
                                    c.category = cc.id');

        $data->rows = array();
        foreach ($syllabus as $item) {

            $teachers = $DB->get_records_sql('   SELECT 
                                            u.id, u.firstname, u.lastname
                                        FROM
                                            {course} c,
                                            {context} ct,
                                            {role_assignments} ra,
                                            {user} u,
                                            {role} r
                                        WHERE
                                            c.id = ct.instanceid
                                                AND ra.contextid = ct.id
                                                AND u.id = ra.userid
                                                AND r.id = ra.roleid
                                                AND r.archetype IN (\'teacher\' , \'editingteacher\')
                                                AND c.id = ?', array($item->id));
            $row = new stdClass();
            if ($item->count >= 1)
                $row->type = get_string('withsyllabus', 'mod_syllabus');
            else
                $row->type = get_string('withoutsyllabus', 'mod_syllabus');

            $row->course = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $item->id . '">' . $item->course . '</a>';
            $row->timemodified = $item->timemodified;
            $row->startdate = $item->startdate;
            $row->enddate = $item->enddate;
            $row->category = $this->breadcrumb($item->category);
            $row->count = $item->count;

            $tmp = array();
            foreach ($teachers as $teacher) {
                array_push($tmp, $teacher->firstname . ' ' . $teacher->lastname);
            }

            $row->owners = implode(', ', $tmp);
            $row->links = $this->get_links($item->id);

            $data->rows[] = $row;
        }

        return $data;
    }
}