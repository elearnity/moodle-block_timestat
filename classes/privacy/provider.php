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
 * Privacy provider for the timestat block.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_timestat\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for data stored by the timestat block.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_timestat',
            [
                'log_id' => 'privacy:metadata:block_timestat:log_id',
                'timespent' => 'privacy:metadata:block_timestat:timespent',
            ],
            'privacy:metadata:block_timestat'
        );
        return $collection;
    }

    /**
     * Add users who have timestat data in the supplied context.
     *
     * @param userlist $userlist Approved context user list.
     */
    public static function get_users_in_context(userlist $userlist) {
        $sql = "SELECT lsl.userid
                  FROM {block_timestat} bt
                  JOIN {logstore_standard_log} lsl ON lsl.id = bt.log_id
                 WHERE lsl.contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, ['contextid' => $userlist->get_context()->id]);
    }

    /**
     * Get contexts containing timestat data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT lsl.contextid
                  FROM {block_timestat} bt
                  JOIN {logstore_standard_log} lsl ON lsl.id = bt.log_id
                 WHERE lsl.userid = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid]);
        return $contextlist;
    }

    /**
     * Export a user's timestat data in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $contextids = $contextlist->get_contextids();
        if (empty($contextids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $sql = "SELECT bt.id, lsl.id AS log_id, bt.timespent,
                       lsl.timecreated AS timestart, lsl.contextid
                  FROM {block_timestat} bt
                  JOIN {logstore_standard_log} lsl ON lsl.id = bt.log_id
                 WHERE lsl.userid = :userid
                       AND lsl.contextid $insql
              ORDER BY lsl.contextid, lsl.timecreated";
        $params = ['userid' => $contextlist->get_user()->id] + $inparams;
        $records = $DB->get_records_sql($sql, $params);

        $data = [];
        foreach ($records as $record) {
            $data[$record->contextid][] = (object)[
                'log_id' => $record->log_id,
                'timespent' => $record->timespent,
                'timestart' => transform::datetime($record->timestart),
            ];
        }

        $path = [get_string('privacy:metadata:block_timestat', 'block_timestat')];
        foreach ($contextlist->get_contexts() as $context) {
            if (!isset($data[$context->id])) {
                continue;
            }
            writer::with_context($context)->export_data(
                $path,
                (object)['block_timestat' => $data[$context->id]]
            );
        }
    }

    /**
     * Delete all timestat data in a context.
     *
     * @param context $context Context whose data should be deleted.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        $select = "log_id IN (
                       SELECT lsl.id
                         FROM {logstore_standard_log} lsl
                        WHERE lsl.contextid = :contextid
                   )";
        $DB->delete_records_select('block_timestat', $select, ['contextid' => $context->id]);
    }

    /**
     * Delete a user's timestat data in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved user and contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $contextids = $contextlist->get_contextids();
        if (empty($contextids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $select = "log_id IN (
                       SELECT lsl.id
                         FROM {logstore_standard_log} lsl
                        WHERE lsl.userid = :userid
                              AND lsl.contextid $insql
                   )";
        $params = ['userid' => $contextlist->get_user()->id] + $inparams;
        $DB->delete_records_select('block_timestat', $select, $params);
    }

    /**
     * Delete timestat data for approved users in one context.
     *
     * @param approved_userlist $userlist Approved context and users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $select = "log_id IN (
                       SELECT lsl.id
                         FROM {logstore_standard_log} lsl
                        WHERE lsl.contextid = :contextid
                              AND lsl.userid $insql
                   )";
        $params = ['contextid' => $userlist->get_context()->id] + $inparams;
        $DB->delete_records_select('block_timestat', $select, $params);
    }
}
