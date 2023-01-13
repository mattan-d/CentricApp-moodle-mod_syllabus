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
 * Provides support for the conversion of moodle1 backup to the moodle2 format
 *
 * @package    mod_syllabus
 * @copyright  2011 Andrew Davis <andrew@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * syllabus conversion handler
 */
class moodle1_mod_syllabus_handler extends moodle1_mod_handler {

    /** @var moodle1_file_manager instance */
    protected $fileman = null;

    /** @var array of syllabus successors handlers */
    private $successors = array();

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances.
     * For each path returned, the corresponding conversion method must be
     * defined.
     *
     * Note that the paths /MOODLE_BACKUP/COURSE/MODULES/MOD/syllabus do not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
        return array(
            new convert_path(
                'syllabus', '/MOODLE_BACKUP/COURSE/MODULES/MOD/syllabus',
                array(
                    'renamefields' => array(
                        'summary' => 'intro',
                    ),
                    'newfields' => array(
                        'introformat' => 0,
                    ),
                    'dropfields' => array(
                        'modtype',
                    ),
                )
            )
        );
    }

    /**
     * Converts /MOODLE_BACKUP/COURSE/MODULES/MOD/syllabus data
     *
     * This methods detects the syllabus type and eventually re-dispatches it to the
     * corresponding syllabus successor (url, forum, page, imscp).
     */
    public function process_syllabus(array $data, array $raw) {
        global $CFG;
        require_once("$CFG->libdir/resourcelib.php");

        // replay the upgrade step 2009042001
        if ($CFG->texteditors !== 'textarea') {
            $data['intro']       = text_to_html($data['intro'], false, false, true);
            $data['introformat'] = FORMAT_HTML;
        }

        // fix invalid null popup and options data
        if (!array_key_exists('popup', $data) or is_null($data['popup'])) {
            $data['popup'] = '';
        }
        if (!array_key_exists ('options', $data) or is_null($data['options'])) {
            $data['options'] = '';
        }

        // decide if the legacy syllabus should be handled by a successor module
        if ($successor = $this->get_successor($data['type'], $data['reference'])) {
            // the instance id will be kept
            $instanceid = $data['id'];

            // move the instance from the syllabus's modinfo stash to the successor's
            // modinfo stash
            $syllabusmodinfo  = $this->converter->get_stash('modinfo_syllabus');
            $successormodinfo = $this->converter->get_stash('modinfo_'.$successor->get_modname());
            $successormodinfo['instances'][$instanceid] = $syllabusmodinfo['instances'][$instanceid];
            unset($syllabusmodinfo['instances'][$instanceid]);
            $this->converter->set_stash('modinfo_syllabus', $syllabusmodinfo);
            $this->converter->set_stash('modinfo_'.$successor->get_modname(), $successormodinfo);

            // get the course module information for the legacy Syllabus module
            $cminfo = $this->get_cminfo($instanceid);

            // use the version of the successor instead of the current mod/syllabus
            // beware - the version.php declares info via $module object, do not use
            // a variable of such name here
            $plugin = new stdClass();
            $plugin->version = null;
            $module = $plugin;
            include $CFG->dirroot.'/mod/'.$successor->get_modname().'/version.php';
            $cminfo['version'] = $plugin->version;

            // stash the new course module information for this successor
            $cminfo['modulename'] = $successor->get_modname();
            $this->converter->set_stash('cminfo_'.$cminfo['modulename'], $cminfo, $instanceid);

            // rewrite the coursecontents stash
            $coursecontents = $this->converter->get_stash('coursecontents');
            $coursecontents[$cminfo['id']]['modulename'] = $successor->get_modname();
            $this->converter->set_stash('coursecontents', $coursecontents);

            // delegate the processing to the successor handler
            return $successor->process_legacy_syllabus($data, $raw);
        }

        // no successor is interested in this record, convert it to the new mod_syllabus (aka File module)

        $syllabus = array();
        $syllabus['id']              = $data['id'];
        $syllabus['name']            = $data['name'];
        $syllabus['intro']           = $data['intro'];
        $syllabus['introformat']     = $data['introformat'];
        $syllabus['tobemigrated']    = 0;
        $syllabus['legacyfiles']     = RESOURCELIB_LEGACYFILES_ACTIVE;
        $syllabus['legacyfileslast'] = null;
        $syllabus['filterfiles']     = 0;
        $syllabus['revision']        = 1;
        $syllabus['timemodified']    = $data['timemodified'];

        // populate display and displayoptions fields
        $options = array('printintro' => 1);
        if ($data['options'] == 'frame') {
            $syllabus['display'] = RESOURCELIB_DISPLAY_FRAME;

        } else if ($data['options'] == 'objectframe') {
            $syllabus['display'] = RESOURCELIB_DISPLAY_EMBED;

        } else if ($data['options'] == 'forcedownload') {
            $syllabus['display'] = RESOURCELIB_DISPLAY_DOWNLOAD;

        } else if ($data['popup']) {
            $syllabus['display'] = RESOURCELIB_DISPLAY_POPUP;
            $rawoptions = explode(',', $data['popup']);
            foreach ($rawoptions as $rawoption) {
                list($name, $value) = explode('=', trim($rawoption), 2);
                if ($value > 0 and ($name == 'width' or $name == 'height')) {
                    $options['popup'.$name] = $value;
                    continue;
                }
            }

        } else {
            $syllabus['display'] = RESOURCELIB_DISPLAY_AUTO;
        }
        $syllabus['displayoptions'] = serialize($options);

        // get the course module id and context id
        $instanceid     = $syllabus['id'];
        $currentcminfo  = $this->get_cminfo($instanceid);
        $moduleid       = $currentcminfo['id'];
        $contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $moduleid);

        // get a fresh new file manager for this instance
        $this->fileman = $this->converter->get_file_manager($contextid, 'mod_syllabus');

        // convert course files embedded into the intro
        $this->fileman->filearea = 'intro';
        $this->fileman->itemid   = 0;
        $syllabus['intro'] = moodle1_converter::migrate_referenced_files($syllabus['intro'], $this->fileman);

        // convert the referenced file itself as a main file in the content area
        $reference = $data['reference'];
        if (strpos($reference, '$@FILEPHP@$') === 0) {
            $reference = str_replace(array('$@FILEPHP@$', '$@SLASH@$', '$@FORCEDOWNLOAD@$'), array('', '/', ''), $reference);
        }
        $this->fileman->filearea = 'content';
        $this->fileman->itemid   = 0;

        // Rebuild the file path.
        $curfilepath = '/';
        if ($reference) {
            $curfilepath = pathinfo('/'.$reference, PATHINFO_DIRNAME);
            if ($curfilepath != '/') {
                $curfilepath .= '/';
            }
        }
        try {
            $this->fileman->migrate_file('course_files/'.$reference, $curfilepath, null, 1);
        } catch (moodle1_convert_exception $e) {
            // the file probably does not exist
            $this->log('error migrating the syllabus main file', backup::LOG_WARNING, 'course_files/'.$reference);
        }

        // write syllabus.xml
        $this->open_xml_writer("activities/syllabus_{$moduleid}/syllabus.xml");
        $this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $moduleid,
            'modulename' => 'syllabus', 'contextid' => $contextid));
        $this->write_xml('syllabus', $syllabus, array('/syllabus/id'));
        $this->xmlwriter->end_tag('activity');
        $this->close_xml_writer();

        // write inforef.xml
        $this->open_xml_writer("activities/syllabus_{$currentcminfo['id']}/inforef.xml");
        $this->xmlwriter->begin_tag('inforef');
        $this->xmlwriter->begin_tag('fileref');
        foreach ($this->fileman->get_fileids() as $fileid) {
            $this->write_xml('file', array('id' => $fileid));
        }
        $this->xmlwriter->end_tag('fileref');
        $this->xmlwriter->end_tag('inforef');
        $this->close_xml_writer();
    }

    /**
     * Give succesors a chance to finish their job
     */
    public function on_syllabus_end(array $data) {
        if ($successor = $this->get_successor($data['type'], $data['reference'])) {
            $successor->on_legacy_syllabus_end($data);
        }
    }

    /// internal implementation details follow /////////////////////////////////

    /**
     * Returns the handler of the new 2.0 mod type according the given type of the legacy 1.9 syllabus
     *
     * @param string $type the value of the 'type' field in 1.9 syllabus
     * @param string $reference a file path. Necessary to differentiate files from web URLs
     * @throws moodle1_convert_exception for the unknown types
     * @return null|moodle1_mod_handler the instance of the handler, or null if the type does not have a successor
     */
    protected function get_successor($type, $reference) {

        switch ($type) {
            case 'text':
            case 'html':
                $name = 'page';
                break;
            case 'directory':
                $name = 'folder';
                break;
            case 'ims':
                $name = 'imscp';
                break;
            case 'file':
                // if starts with $@FILEPHP@$ then it is URL link to a local course file
                // to be migrated to the new Syllabus module
                if (strpos($reference, '$@FILEPHP@$') === 0) {
                    $name = null;
                    break;
                }
                // if http:// https:// ftp:// OR starts with slash need to be converted to URL
                if (strpos($reference, '://') or strpos($reference, '/') === 0) {
                    $name = 'url';
                } else {
                    $name = null;
                }
                break;
            default:
                throw new moodle1_convert_exception('unknown_syllabus_successor', $type);
        }

        if (is_null($name)) {
            return null;
        }

        if (!isset($this->successors[$name])) {
            $this->log('preparing syllabus successor handler', backup::LOG_DEBUG, $name);
            $class = 'moodle1_mod_'.$name.'_handler';
            $this->successors[$name] = new $class($this->converter, 'mod', $name);

            // add the successor into the modlist stash
            $modnames = $this->converter->get_stash('modnameslist');
            $modnames[] = $name;
            $modnames = array_unique($modnames); // should not be needed but just in case
            $this->converter->set_stash('modnameslist', $modnames);

            // add the successor's modinfo stash
            $modinfo = $this->converter->get_stash('modinfo_syllabus');
            $modinfo['name'] = $name;
            $modinfo['instances'] = array();
            $this->converter->set_stash('modinfo_'.$name, $modinfo);
        }

        return $this->successors[$name];
     }
}
