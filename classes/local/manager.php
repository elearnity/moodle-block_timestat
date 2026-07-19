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

namespace block_timestat\local;

use core\log\sql_reader;
use moodle_exception;
use stdClass;

/**
 * Locates relevant log entries for Timestat via the log store API.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * Get the current user's latest log entry for a context via the log reader API.
     *
     * @param int $contextid
     * @param int|null $userid Defaults to the current user.
     * @return stdClass Object with id, userid, contextid, courseid, contextlevel, contextinstanceid.
     * @throws moodle_exception
     */
    public static function get_user_last_log_by_contextid(int $contextid, ?int $userid = null): stdClass {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        $store = self::get_sql_reader();
        $events = $store->get_events_select(
            'contextid = :contextid AND userid = :userid',
            [
                'contextid' => $contextid,
                'userid' => $userid,
            ],
            'timecreated DESC',
            0,
            1
        );

        if (empty($events)) {
            throw new moodle_exception('nologs', 'block_timestat');
        }

        $logid = (int) array_key_first($events);
        $event = $events[$logid];
        $data = $event->get_data();

        return (object) [
            'id' => $logid,
            'userid' => (int) $data['userid'],
            'contextid' => (int) $data['contextid'],
            'courseid' => (int) ($data['courseid'] ?? 0),
            'contextlevel' => (int) ($data['contextlevel'] ?? 0),
            'contextinstanceid' => (int) ($data['contextinstanceid'] ?? 0),
            'timecreated' => (int) ($data['timecreated'] ?? 0),
            'event' => $event,
        ];
    }

    /**
     * Return the first available SQL log reader.
     *
     * @return sql_reader
     * @throws moodle_exception
     */
    private static function get_sql_reader(): sql_reader {
        $readers = get_log_manager()->get_readers(sql_reader::class);
        if (empty($readers)) {
            throw new moodle_exception('nologreader', 'block_timestat');
        }

        return reset($readers);
    }
}
