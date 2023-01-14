<?php
// Standard GPL and phpdocs
namespace mod_syllabus\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;

class renderer extends plugin_renderer_base
{
    /**
     * Defer to template.
     *
     * @param report $page
     *
     * @return string html for the page
     */
    public function render_report($page)
    {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_syllabus/report_page', $data);
    }
}