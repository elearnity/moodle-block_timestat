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
 * Tests for timestat report queries.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_timestat;

/**
 * Tests for timestat report queries.
 *
 */
final class locallib_test extends \advanced_testcase {
    /**
     * Load the function library.
     */
    protected function setUp(): void {
        parent::setUp();
        require_once(__DIR__ . '/../locallib.php');
    }

    /**
     * The report must not expose users from another separate group.
     *
     * @covers ::block_timestat_build_logs_array()
     */
    public function test_selected_user_does_not_bypass_separate_groups(): void {
        global $SESSION;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $useringroup = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $useroutsidegroup = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $othergroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $viewer->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $useringroup->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $othergroup->id, 'userid' => $useroutsidegroup->id]);
        $this->create_timestat_record($course, $useringroup, 30);
        $this->create_timestat_record($course, $useroutsidegroup, 60);

        $this->setUser($viewer);
        $SESSION->currentgroup[$course->id] = $group->id;

        $outside = block_timestat_build_logs_array($course, $useroutsidegroup->id);
        $this->assertSame(0, $outside['totalcount']);
        $this->assertSame([], $outside['logs']);

        $inside = block_timestat_build_logs_array($course, $useringroup->id);
        $this->assertSame(1, $inside['totalcount']);
        $this->assertArrayHasKey($useringroup->id, $inside['logs']);
    }

    /**
     * A user without a group must not see all users in separate-groups mode.
     *
     * @covers ::block_timestat_build_logs_array()
     */
    public function test_ungrouped_viewer_sees_no_separate_group_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $trackeduser = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $trackeduser->id]);
        $this->create_timestat_record($course, $trackeduser, 30);

        $this->setUser($viewer);
        $result = block_timestat_build_logs_array($course);

        $this->assertSame(0, $result['totalcount']);
        $this->assertSame([], $result['logs']);
    }

    /**
     * Report aggregation must remain scoped to the selected course.
     *
     * @covers ::block_timestat_build_logs_array()
     */
    public function test_report_is_scoped_to_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $courseuser = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otheruser = $this->getDataGenerator()->create_and_enrol($othercourse, 'student');
        $this->create_timestat_record($course, $courseuser, 30);
        $this->create_timestat_record($othercourse, $otheruser, 60);

        $this->setAdminUser();
        $result = block_timestat_build_logs_array($course);

        $this->assertSame(1, $result['totalcount']);
        $this->assertArrayHasKey($courseuser->id, $result['logs']);
        $this->assertArrayNotHasKey($otheruser->id, $result['logs']);
        $this->assertEquals(30, $result['logs'][$courseuser->id]->timespent);
    }

    /**
     * Create a standard log row and its timestat value.
     *
     * @param \stdClass $course Course record.
     * @param \stdClass $user User record.
     * @param int $timespent Tracked seconds.
     * @return int Timestat record ID.
     */
    private function create_timestat_record(\stdClass $course, \stdClass $user, int $timespent): int {
        global $DB;

        $context = \context_course::instance($course->id);
        $logid = $DB->insert_record('logstore_standard_log', (object)[
            'eventname' => '\core\event\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => \core\event\base::LEVEL_PARTICIPATING,
            'contextid' => $context->id,
            'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid,
            'userid' => $user->id,
            'courseid' => $course->id,
            'timecreated' => time(),
        ]);

        return $DB->insert_record('block_timestat', (object)[
            'log_id' => $logid,
            'timespent' => $timespent,
        ]);
    }
}
