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

/**
 * Seed timestat test data for enrolled users in a course using the log API.
 *
 * @package    block_timestat
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use block_timestat\local\manager;
use block_timestat\local\timestat;

[$options, $unrecognized] = cli_get_params([
    'courseid' => 4,
    'help' => false,
], [
    'c' => 'courseid',
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Seed block_timestat test records for enrolled users.\n\n";
    echo "Options:\n";
    echo "-c, --courseid   Course id (default 4)\n";
    echo "-h, --help       Print this help\n";
    exit(0);
}

// Ensure the standard log store is enabled and writes immediately.
set_config('enabled_stores', 'logstore_standard', 'tool_log');
set_config('buffersize', 0, 'logstore_standard');
set_config('logguests', 1, 'logstore_standard');
get_log_manager(true);

$courseid = (int) $options['courseid'];
$course = get_course($courseid);
$coursecontext = context_course::instance($courseid);
$users = get_enrolled_users($coursecontext);

if (!$users) {
    cli_error("No enrolled users found in course {$courseid}");
}

$cms = $DB->get_records_sql(
    "SELECT cm.id, cm.instance, m.name AS modname
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module
      WHERE cm.course = :courseid
        AND cm.deletioninprogress = 0",
    ['courseid' => $courseid]
);

cli_writeln("Course: {$course->fullname} ({$courseid})");
cli_writeln('Enrolled users: ' . count($users));
cli_writeln('Activities/resources: ' . count($cms));

$inserted = 0;

foreach ($users as $user) {
    \core\session\manager::set_user($user);

    \core\event\course_viewed::create(['context' => $coursecontext])->trigger();
    $log = manager::get_user_last_log_by_contextid($coursecontext->id, (int) $user->id);
    timestat::upsert_from_log($log, 300 + (($user->id * 37) % 900));
    $inserted++;

    $samplecms = array_slice(array_values($cms), 0, 3);
    foreach ($samplecms as $index => $cm) {
        $eventclass = '\\mod_' . $cm->modname . '\\event\\course_module_viewed';
        if (!class_exists($eventclass)) {
            continue;
        }

        $cmcontext = context_module::instance($cm->id);
        $eventclass::create([
            'objectid' => $cm->instance,
            'context' => $cmcontext,
        ])->trigger();

        $log = manager::get_user_last_log_by_contextid($cmcontext->id, (int) $user->id);
        timestat::upsert_from_log($log, 60 + (($user->id + $cm->id + $index) % 540));
        $inserted++;
    }

    cli_writeln("  Seeded user {$user->id} (" . fullname($user) . ')');
}

cli_writeln("Done. Inserted {$inserted} timestat records.");
