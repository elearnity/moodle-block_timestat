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
 * Scheduled cleanup of timestat rows whose standard log record was deleted.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_timestat\task;

/**
 * Delete timestat rows orphaned by standard log retention.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_orphaned_records extends \core\task\scheduled_task {
    /**
     * Return the translated task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('cleanuporphanedtask', 'block_timestat');
    }

    /**
     * Delete orphaned rows in bounded batches.
     */
    public function execute(): void {
        global $DB;

        do {
            $sql = "SELECT bt.id
                      FROM {block_timestat} bt
                 LEFT JOIN {logstore_standard_log} l ON l.id = bt.log_id
                     WHERE l.id IS NULL";
            $ids = $DB->get_fieldset_sql($sql, [], 0, 1000);
            if ($ids) {
                $DB->delete_records_list('block_timestat', 'id', $ids);
            }
        } while (count($ids) === 1000);
    }
}
