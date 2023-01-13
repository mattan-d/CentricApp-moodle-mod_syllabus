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
 * Events test.
 *
 * @package    mod_syllabus
 * @copyright  2014 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\event;

/**
 * syllabus events test cases.
 *
 * @package    mod_syllabus
 * @copyright  2014 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    /**
     * Setup is called before calling test case.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        // Must be a non-guest user to create syllabuss.
        $this->setAdminUser();
    }

    /**
     * Test course_module_instance_list_viewed event.
     */
    public function test_course_module_instance_list_viewed() {
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $course = $this->getDataGenerator()->create_course();
        $params = array(
            'context' => \context_course::instance($course->id)
        );
        $event = \mod_syllabus\event\course_module_instance_list_viewed::create($params);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_syllabus\event\course_module_instance_list_viewed', $event);
        $this->assertEquals(\context_course::instance($course->id), $event->get_context());
        $expected = array($course->id, 'syllabus', 'view all', 'index.php?id='.$course->id, '');
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test course_module_viewed event.
     */
    public function test_course_module_viewed() {
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', array('course' => $course->id));

        $params = array(
            'context' => \context_module::instance($syllabus->cmid),
            'objectid' => $syllabus->id
        );
        $event = \mod_syllabus\event\course_module_viewed::create($params);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_syllabus\event\course_module_viewed', $event);
        $this->assertEquals(\context_module::instance($syllabus->cmid), $event->get_context());
        $this->assertEquals($syllabus->id, $event->objectid);
        $expected = array($course->id, 'syllabus', 'view', 'view.php?id=' . $syllabus->cmid, $syllabus->id, $syllabus->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }
}
