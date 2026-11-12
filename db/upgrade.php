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
 * Upgrade steps for the timestat block.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the timestat block.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_block_timestat_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026111200) {
        $table = new xmldb_table('block_timestat');
        $index = new xmldb_index('logid', XMLDB_INDEX_UNIQUE, ['log_id']);

        if (!$dbman->index_exists($table, $index)) {
            // Keep the greatest accumulated value if concurrent requests created duplicate rows.
            $duplicates = $DB->get_records_sql(
                "SELECT log_id, MAX(timespent) AS timespent
                   FROM {block_timestat}
               GROUP BY log_id
                 HAVING COUNT(*) > 1"
            );
            foreach ($duplicates as $duplicate) {
                $DB->delete_records('block_timestat', ['log_id' => $duplicate->log_id]);
                $DB->insert_record('block_timestat', (object)[
                    'log_id' => $duplicate->log_id,
                    'timespent' => $duplicate->timespent,
                ]);
            }

            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026111200, 'timestat');
    }

    return true;
}
