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

namespace block_timestat;

use block_timestat\local\timestat;
use context_module;

/**
 * Public API for other plugins (e.g. reports) to query dedication time.
 *
 * Callers are responsible for authentication and capability checks.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Total time spent by a user in a course (seconds).
     *
     * Sums all Timestat records for the course, including activity contexts.
     *
     * @param int $courseid
     * @param int $userid
     * @return int
     */
    public static function get_course_timespent(int $courseid, int $userid): int {
        return timestat::get_user_course_timespent($courseid, $userid);
    }

    /**
     * Total time spent by a user in a course activity (seconds).
     *
     * @param int $cmid Course module id
     * @param int $userid
     * @return int
     */
    public static function get_activity_timespent(int $cmid, int $userid): int {
        $context = context_module::instance($cmid);
        return timestat::get_user_context_timespent($context->id, $userid);
    }

    /**
     * Total time spent by a user in a specific context (seconds).
     *
     * @param int $contextid
     * @param int $userid
     * @return int
     */
    public static function get_context_timespent(int $contextid, int $userid): int {
        return timestat::get_user_context_timespent($contextid, $userid);
    }
}
