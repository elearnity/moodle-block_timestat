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

use core\persistent;
use stdClass;

/**
 * Persistent model for block_timestat records.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property int $id
 * @property int $log_id
 * @property int $timespent
 * @property int $userid
 * @property int $courseid
 * @property int $contextid
 * @property int $contextlevel
 * @property int $contextinstanceid
 * @property int $usermodified
 * @property int $timecreated
 * @property int $timemodified
 */
class timestat extends persistent {

    /** The table name. */
    public const TABLE = 'block_timestat';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'log_id' => [
                'type' => PARAM_INT,
            ],
            'timespent' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'courseid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'contextid' => [
                'type' => PARAM_INT,
            ],
            'contextlevel' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'contextinstanceid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Get the total time (in seconds) a user has spent in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return int
     */
    public static function get_user_course_timespent(int $courseid, int $userid): int {
        $records = self::get_records([
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        $total = 0;
        foreach ($records as $record) {
            $total += (int) $record->get('timespent');
        }

        return $total;
    }

    /**
     * Get the total time (in seconds) a user has spent in a specific context.
     *
     * @param int $contextid
     * @param int $userid
     * @return int
     */
    public static function get_user_context_timespent(int $contextid, int $userid): int {
        $records = self::get_records([
            'contextid' => $contextid,
            'userid' => $userid,
        ]);

        $total = 0;
        foreach ($records as $record) {
            $total += (int) $record->get('timespent');
        }

        return $total;
    }

    /**
     * Create or update the timespent for a log entry returned by the log reader API.
     *
     * @param stdClass $log Must include id, userid, courseid, contextid, and optionally
     *                      contextlevel / contextinstanceid / event.
     * @param int $timespent
     * @return self
     */
    public static function upsert_from_log(stdClass $log, int $timespent): self {
        $contextlevel = (int) ($log->contextlevel ?? 0);
        $contextinstanceid = (int) ($log->contextinstanceid ?? 0);

        if ((!$contextlevel || !$contextinstanceid) && !empty($log->event) && $log->event instanceof \core\event\base) {
            $data = $log->event->get_data();
            $contextlevel = (int) ($data['contextlevel'] ?? $contextlevel);
            $contextinstanceid = (int) ($data['contextinstanceid'] ?? $contextinstanceid);
        }

        if ((!$contextlevel || !$contextinstanceid) && !empty($log->contextid)) {
            $context = \context::instance_by_id((int) $log->contextid, IGNORE_MISSING);
            if ($context) {
                $contextlevel = (int) $context->contextlevel;
                $contextinstanceid = (int) $context->instanceid;
            }
        }

        $record = self::get_record(['log_id' => (int) $log->id]);
        if ($record) {
            $record->set('timespent', $timespent);
            $record->set('userid', (int) $log->userid);
            $record->set('courseid', (int) ($log->courseid ?? 0));
            $record->set('contextid', (int) $log->contextid);
            $record->set('contextlevel', $contextlevel);
            $record->set('contextinstanceid', $contextinstanceid);
            $record->update();
            return $record;
        }

        $record = new self(0, (object) [
            'log_id' => (int) $log->id,
            'timespent' => $timespent,
            'userid' => (int) $log->userid,
            'courseid' => (int) ($log->courseid ?? 0),
            'contextid' => (int) $log->contextid,
            'contextlevel' => $contextlevel,
            'contextinstanceid' => $contextinstanceid,
            // Align report timestamps with the related log entry when available.
            'timecreated' => (int) ($log->timecreated ?? time()),
        ]);
        $record->create();

        return $record;
    }
}
