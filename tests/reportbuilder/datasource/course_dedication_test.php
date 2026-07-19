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

declare(strict_types=1);

namespace block_timestat\reportbuilder\datasource;

use block_timestat\local\manager;
use block_timestat\local\timestat;
use context;
use context_course;
use context_module;
use core_reportbuilder_generator;
use core_reportbuilder\local\filters\{date, duration, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Unit tests for course dedication datasource.
 *
 * @package    block_timestat
 * @covers     \block_timestat\reportbuilder\datasource\course_dedication
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_dedication_test extends core_reportbuilder_testcase {

    /**
     * Enable the standard log store for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
        get_log_manager(true);
    }

    /**
     * Trigger a real event, then attach a timestat record via the log reader API.
     *
     * @param context $context
     * @param stdClass $user
     * @param int $timespent
     * @param stdClass|null $page Page module record when creating a module viewed event.
     * @return stdClass Log summary with id and timecreated.
     */
    private function create_timestat_record(context $context, stdClass $user, int $timespent,
            ?stdClass $page = null): stdClass {
        $this->setUser($user);

        if ($context instanceof context_module && $page !== null) {
            \mod_page\event\course_module_viewed::create([
                'objectid' => $page->id,
                'context' => $context,
            ])->trigger();
        } else {
            \core\event\course_viewed::create([
                'context' => $context,
            ])->trigger();
        }

        $log = manager::get_user_last_log_by_contextid($context->id, (int) $user->id);
        timestat::upsert_from_log($log, $timespent);

        return $log;
    }

    /**
     * Test default datasource.
     */
    public function test_datasource_default(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Dedication course']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Alice',
            'lastname' => 'Anderson',
        ]);
        $coursecontext = context_course::instance($course->id);

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Reading page',
        ]);
        $cmcontext = context_module::instance($page->cmid);

        $courselog = $this->create_timestat_record($coursecontext, $user, 120);
        $activitylog = $this->create_timestat_record($cmcontext, $user, 300, $page);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Course dedication',
            'source' => course_dedication::class,
            'default' => 1,
        ]);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(2, $content);

        $activityname = get_fast_modinfo($course)->get_cm($page->cmid)->get_formatted_name();

        // Default columns: user, course, activity, timespent, timecreated.
        // Sorted by user ASC, timespent DESC.
        $this->assertEquals([
            [
                fullname($user),
                $course->fullname,
                $activityname,
                format::format_time(300, (object) []),
                userdate($activitylog->timecreated),
            ],
            [
                fullname($user),
                $course->fullname,
                get_string('course'),
                format::format_time(120, (object) []),
                userdate($courselog->timecreated),
            ],
        ], array_map('array_values', $content));
    }

    /**
     * Test datasource columns that aren't added by default.
     */
    public function test_datasource_non_default_columns(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cmcontext = context_module::instance($page->cmid);

        $this->create_timestat_record($cmcontext, $user, 90, $page);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Course dedication',
            'source' => course_dedication::class,
            'default' => 0,
        ]);

        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'timestat:cmid']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'timestat:timespent']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        $this->assertEquals([
            (string) $page->cmid,
            format::format_time(90, (object) []),
        ], array_values($content[0]));
    }

    /**
     * Data provider for {@see test_datasource_filters}.
     *
     * @return array[]
     */
    public static function datasource_filters_provider(): array {
        return [
            'Filter timespent' => [
                'timestat:timespent',
                [
                    'timestat:timespent_operator' => duration::DURATION_MINIMUM,
                    'timestat:timespent_unit' => MINSECS,
                    'timestat:timespent_value' => 2,
                ],
                true,
            ],
            'Filter timespent (no match)' => [
                'timestat:timespent',
                [
                    'timestat:timespent_operator' => duration::DURATION_MINIMUM,
                    'timestat:timespent_unit' => MINSECS,
                    'timestat:timespent_value' => 10,
                ],
                false,
            ],
            'Filter timecreated' => [
                'timestat:timecreated',
                [
                    'timestat:timecreated_operator' => date::DATE_RANGE,
                    'timestat:timecreated_from' => 1622502000,
                ],
                true,
            ],
            'Filter timecreated (no match)' => [
                'timestat:timecreated',
                [
                    'timestat:timecreated_operator' => date::DATE_RANGE,
                    'timestat:timecreated_to' => 1622502000,
                ],
                false,
            ],
            'Filter user' => [
                'user:fullname',
                [
                    'user:fullname_operator' => text::IS_EQUAL_TO,
                    'user:fullname_value' => 'Match User',
                ],
                true,
            ],
            'Filter user (no match)' => [
                'user:fullname',
                [
                    'user:fullname_operator' => text::IS_EQUAL_TO,
                    'user:fullname_value' => 'Somebody Else',
                ],
                false,
            ],
        ];
    }

    /**
     * Test datasource filters.
     *
     * @param string $filtername
     * @param array $filtervalues
     * @param bool $expectmatch
     *
     * @dataProvider datasource_filters_provider
     */
    public function test_datasource_filters(string $filtername, array $filtervalues, bool $expectmatch): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Match',
            'lastname' => 'User',
        ]);
        $coursecontext = context_course::instance($course->id);

        $this->create_timestat_record($coursecontext, $user, 180);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Course dedication',
            'source' => course_dedication::class,
            'default' => 0,
        ]);

        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'timestat:timespent']);
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => $filtername]);

        $content = $this->get_custom_report_content($report->get('id'), 0, $filtervalues);

        if ($expectmatch) {
            $this->assertCount(1, $content);
        } else {
            $this->assertEmpty($content);
        }
    }

    /**
     * Stress test datasource.
     *
     * In order to execute this test PHPUNIT_LONGTEST should be defined as true in phpunit.xml or directly in config.php.
     */
    public function test_stress_datasource(): void {
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $coursecontext = context_course::instance($course->id);

        $this->create_timestat_record($coursecontext, $user, 60);

        $this->datasource_stress_test_columns(course_dedication::class);
        $this->datasource_stress_test_columns_aggregation(course_dedication::class);
        $this->datasource_stress_test_conditions(course_dedication::class, 'timestat:timespent');
    }
}
