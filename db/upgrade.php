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
 * Upgrade steps for block_timestat.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the block_timestat plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_timestat_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071905) {
        $table = new xmldb_table('block_timestat');

        $fields = [
            new xmldb_field('timespent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'log_id'),
            new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timespent'),
            new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid'),
            new xmldb_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid'),
            new xmldb_field('contextlevel', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'contextid'),
            new xmldb_field('contextinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'contextlevel'),
            new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'contextinstanceid'),
            new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'usermodified'),
            new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'),
        ];

        foreach ($fields as $field) {
            if ($field->getName() === 'timespent') {
                // Existing installs already have timespent, ensure NOT NULL default.
                if ($dbman->field_exists($table, $field)) {
                    $DB->set_field_select('block_timestat', 'timespent', 0, 'timespent IS NULL');
                    $dbman->change_field_notnull($table, $field);
                    $dbman->change_field_default($table, $field);
                } else {
                    $dbman->add_field($table, $field);
                }
                continue;
            }
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Backfill denormalised log dimensions from the active SQL log store when available.
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers(\core\log\sql_internal_table_reader::class);
        if (!empty($readers)) {
            /** @var \core\log\sql_internal_table_reader $reader */
            $reader = reset($readers);
            $logtable = $reader->get_internal_log_table_name();
            $records = $DB->get_recordset('block_timestat');
            $now = time();
            foreach ($records as $record) {
                $log = $DB->get_record($logtable, ['id' => $record->log_id]);
                if (!$log) {
                    continue;
                }
                $record->userid = (int) $log->userid;
                $record->courseid = (int) ($log->courseid ?? 0);
                $record->contextid = (int) $log->contextid;
                $record->contextlevel = (int) $log->contextlevel;
                $record->contextinstanceid = (int) $log->contextinstanceid;
                $record->timespent = (int) ($record->timespent ?? 0);
                $record->usermodified = (int) $log->userid;
                $record->timecreated = (int) ($log->timecreated ?? $now);
                $record->timemodified = $now;
                $DB->update_record('block_timestat', $record);
            }
            $records->close();
        }

        $key = new xmldb_key('log_id', XMLDB_KEY_UNIQUE, ['log_id']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        $key = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        $index = new xmldb_index('userid-courseid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('userid-contextid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'contextid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026071905, 'timestat');
    }

    return true;
}
