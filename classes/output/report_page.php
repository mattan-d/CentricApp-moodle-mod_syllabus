<?php
// Standard GPL and phpdocs
namespace mod_syllabus\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

class report_page implements renderable, templatable
{

    public function __construct($data)
    {
        $this->results = $data;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output)
    {
        $data = new stdClass();
        $data->rows = [];
        foreach ($this->results as $row) {
            $data->rows[] = $row;
        }

        return $data;
    }
}