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
 * Tests for the timestat privacy provider.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_timestat\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the timestat privacy provider.
 *
 * @covers \block_timestat\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Context discovery must return real context IDs.
     */
    public function test_get_contexts_for_userid_returns_contextids(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $othercontext = \context_course::instance($othercourse->id);
        $this->create_timestat_record($coursecontext, $user, 10);
        $this->create_timestat_record($othercontext, $user, 20);

        $actual = provider::get_contexts_for_userid($user->id)->get_contextids();

        sort($actual);
        $expected = [$coursecontext->id, $othercontext->id];
        sort($expected);
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    /**
     * Export must use the selected log ID and approved context.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->create_timestat_record($context, $user, 42);

        $approved = new approved_contextlist($user, 'block_timestat', [$context->id]);
        provider::export_user_data($approved);

        $path = [get_string('privacy:metadata:block_timestat', 'block_timestat')];
        $data = writer::with_context($context)->get_data($path);
        $this->assertCount(1, $data->block_timestat);
        $this->assertEquals(42, $data->block_timestat[0]->timespent);
        $this->assertNotEmpty($data->block_timestat[0]->log_id);
    }

    /**
     * User deletion must only affect approved contexts.
     */
    public function test_delete_data_for_user_is_context_scoped(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $othercontext = \context_course::instance($othercourse->id);
        $deletedid = $this->create_timestat_record($coursecontext, $user, 10);
        $retainedcontextid = $this->create_timestat_record($othercontext, $user, 20);
        $retaineduserid = $this->create_timestat_record($coursecontext, $otheruser, 30);

        $approved = new approved_contextlist($user, 'block_timestat', [$coursecontext->id]);
        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('block_timestat', ['id' => $deletedid]));
        $this->assertTrue($DB->record_exists('block_timestat', ['id' => $retainedcontextid]));
        $this->assertTrue($DB->record_exists('block_timestat', ['id' => $retaineduserid]));
    }

    /**
     * Bulk user deletion must be restricted to both context and approved users.
     */
    public function test_delete_data_for_users_is_context_and_user_scoped(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $othercontext = \context_course::instance($othercourse->id);
        $deletedid = $this->create_timestat_record($coursecontext, $user, 10);
        $retainedcontextid = $this->create_timestat_record($othercontext, $user, 20);
        $retaineduserid = $this->create_timestat_record($coursecontext, $otheruser, 30);

        $approved = new approved_userlist($coursecontext, 'block_timestat', [$user->id]);
        provider::delete_data_for_users($approved);

        $this->assertFalse($DB->record_exists('block_timestat', ['id' => $deletedid]));
        $this->assertTrue($DB->record_exists('block_timestat', ['id' => $retainedcontextid]));
        $this->assertTrue($DB->record_exists('block_timestat', ['id' => $retaineduserid]));
    }

    /**
     * Users in a context must be discovered through log context IDs.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $othercontext = \context_course::instance($othercourse->id);
        $this->create_timestat_record($context, $user, 10);
        $this->create_timestat_record($othercontext, $otheruser, 20);

        $userlist = new userlist($context, 'block_timestat');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing([$user->id], $userlist->get_userids());
    }

    /**
     * Create a standard log row and linked timestat value.
     *
     * @param \context $context Context owning the data.
     * @param \stdClass $user User record.
     * @param int $timespent Tracked seconds.
     * @return int Timestat record ID.
     */
    private function create_timestat_record(\context $context, \stdClass $user, int $timespent): int {
        global $DB;

        $courseid = $context->get_course_context(false)->instanceid;
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
            'courseid' => $courseid,
            'timecreated' => time(),
        ]);

        return $DB->insert_record('block_timestat', (object)[
            'log_id' => $logid,
            'timespent' => $timespent,
        ]);
    }
}
