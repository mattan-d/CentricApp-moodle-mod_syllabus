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
 * Defines backup_syllabus_activity_task class
 *
 * @package     mod_syllabus
 * @category    backup
 * @copyright   2023 CentricApp  {@link https://centricapp.co}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/syllabus/backup/moodle2/backup_syllabus_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the syllabus instance
 */
class backup_syllabus_activity_task extends backup_activity_task
{

    /**
     * @param bool $syllabusoldexists True if there are records in the syllabus_old table.
     */
    protected static $syllabusoldexists = null;

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings()
    {
    }

    /**
     * Defines a backup step to store the instance data in the syllabus.xml file
     */
    protected function define_my_steps()
    {
        $this->add_step(new backup_syllabus_activity_structure_step('syllabus_structure', 'syllabus.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    static public function encode_content_links($content)
    {
        global $CFG, $DB;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of syllabuss.
        $search = "/(" . $base . "\/mod\/syllabus\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@SYLLABUSINDEX*$2@$', $content);

        // Link to syllabus view by moduleid.
        $search = "/(" . $base . "\/mod\/syllabus\/view.php\?id\=)([0-9]+)/";
        // Link to syllabus view by recordid
        $search2 = "/(" . $base . "\/mod\/syllabus\/view.php\?r\=)([0-9]+)/";

        // Check whether there are contents in the syllabus old table.
        if (static::$syllabusoldexists === null) {
            static::$syllabusoldexists = $DB->record_exists('syllabus_old', array());
        }

        // If there are links to items in the syllabus_old table, rewrite them to be links to the correct URL
        // for their new module.
        if (static::$syllabusoldexists) {
            // Match all of the syllabuss.
            $result = preg_match_all($search, $content, $matches, PREG_PATTERN_ORDER);

            // Course module ID syllabus links.
            if ($result) {
                list($insql, $params) = $DB->get_in_or_equal($matches[2]);
                $oldrecs = $DB->get_records_select('syllabus_old', "cmid $insql", $params, '', 'cmid, newmodule');

                for ($i = 0; $i < count($matches[0]); $i++) {
                    $cmid = $matches[2][$i];
                    if (isset($oldrecs[$cmid])) {
                        // syllabus_old item, rewrite it
                        $replace = '$@' . strtoupper($oldrecs[$cmid]->newmodule) . 'VIEWBYID*' . $cmid . '@$';
                    } else {
                        // Not in the syllabus old table, don't rewrite
                        $replace = '$@SYLLABUSVIEWBYID*' . $cmid . '@$';
                    }
                    $content = str_replace($matches[0][$i], $replace, $content);
                }
            }

            $matches = null;
            $result = preg_match_all($search2, $content, $matches, PREG_PATTERN_ORDER);

            // No syllabus links.
            if (!$result) {
                return $content;
            }
            // syllabus ID links.
            list($insql, $params) = $DB->get_in_or_equal($matches[2]);
            $oldrecs = $DB->get_records_select('syllabus_old', "oldid $insql", $params, '', 'oldid, cmid, newmodule');

            for ($i = 0; $i < count($matches[0]); $i++) {
                $recordid = $matches[2][$i];
                if (isset($oldrecs[$recordid])) {
                    // syllabus_old item, rewrite it
                    $replace = '$@' . strtoupper($oldrecs[$recordid]->newmodule) . 'VIEWBYID*' . $oldrecs[$recordid]->cmid . '@$';
                    $content = str_replace($matches[0][$i], $replace, $content);
                }
            }
        } else {
            $content = preg_replace($search, '$@SYLLABUSVIEWBYID*$2@$', $content);
        }
        return $content;
    }
}
