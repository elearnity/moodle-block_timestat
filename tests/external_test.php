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
 * Tests for the timestat external API.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_timestat;

/**
 * Tests for the timestat external API.
 *
 * @covers \block_timestat\external
 */
final class external_test extends \core_external\tests\externallib_testcase {
    /**
     * A missing standard log row should not cause a fatal error.
     */
    public function test_update_register_without_log_returns_cleanly(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $this->assertSame([], external::update_register(10, \context_course::instance($course->id)->id));
    }

    /**
     * Client time is bounded by elapsed server time.
     */
    public function test_update_register_caps_client_time(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_course::instance($course->id);
        $logid = $this->create_log($context, $user, time() - 5);
        $this->setUser($user);

        external::update_register(9999999, $context->id);
        $record = $DB->get_record('block_timestat', ['log_id' => $logid], '*', MUST_EXIST);

        $this->assertGreaterThanOrEqual(5, $record->timespent);
        $this->assertLessThanOrEqual(10, $record->timespent);
    }

    /**
     * Negative client time is stored as zero.
     */
    public function test_update_register_rejects_negative_time(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_course::instance($course->id);
        $logid = $this->create_log($context, $user, time() - 5);
        $this->setUser($user);

        external::update_register(-20, $context->id);
        $record = $DB->get_record('block_timestat', ['log_id' => $logid], '*', MUST_EXIST);

        $this->assertSame(0, (int)$record->timespent);
    }

    /**
     * Create a standard log row.
     *
     * @param \context $context Log context.
     * @param \stdClass $user User record.
     * @param int $timecreated Creation timestamp.
     * @return int Log record ID.
     */
    private function create_log(\context $context, \stdClass $user, int $timecreated): int {
        global $DB;

        return $DB->insert_record('logstore_standard_log', (object)[
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
            'courseid' => $context->instanceid,
            'timecreated' => $timecreated,
        ]);
    }
}
