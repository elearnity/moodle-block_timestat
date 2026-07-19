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

namespace block_timestat\privacy;

use block_timestat\local\timestat;
use coding_exception;
use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{approved_contextlist, approved_userlist, contextlist, core_userlist_provider, transform, userlist,
    writer};

/**
 * Privacy provider for block_timestat.
 *
 * @package    block_timestat
 * @copyright  2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    /**
     * Returns metadata about this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            timestat::TABLE,
            [
                'log_id' => 'privacy:metadata:block_timestat:log_id',
                'timespent' => 'privacy:metadata:block_timestat:timespent',
                'userid' => 'privacy:metadata:block_timestat:userid',
                'courseid' => 'privacy:metadata:block_timestat:courseid',
                'contextid' => 'privacy:metadata:block_timestat:contextid',
            ],
            'privacy:metadata:block_timestat'
        );
        return $collection;
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        // Time is stored against course/module contexts.
        $records = timestat::get_records(['contextid' => $context->id]);
        $userids = [];
        foreach ($records as $record) {
            $userids[] = (int) $record->get('userid');
        }
        if (!empty($userids)) {
            $userlist->add_users($userids);
        }
    }

    /**
     * Delete data for users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        foreach ($userlist->get_userids() as $userid) {
            $records = timestat::get_records([
                'userid' => $userid,
                'contextid' => $context->id,
            ]);
            foreach ($records as $record) {
                $record->delete();
            }
        }
    }

    /**
     * Get contexts for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $records = timestat::get_records(['userid' => $userid]);
        if (empty($records)) {
            return $contextlist;
        }

        $contextids = [];
        foreach ($records as $record) {
            $contextids[(int) $record->get('contextid')] = true;
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($contextids), SQL_PARAMS_NAMED);
        $contextlist->add_from_sql("SELECT id FROM {context} WHERE id {$insql}", $params);

        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     * @throws coding_exception
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        $records = timestat::get_records(['userid' => $userid]);
        if (empty($records)) {
            return;
        }

        $grouped = [];
        foreach ($records as $record) {
            $contextid = (int) $record->get('contextid');
            $grouped[$contextid][] = (object) [
                'log_id' => (int) $record->get('log_id'),
                'timespent' => (int) $record->get('timespent'),
                'timestart' => transform::datetime((int) $record->get('timecreated')),
            ];
        }

        foreach ($contextlist as $context) {
            if (!isset($grouped[$context->id])) {
                continue;
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:metadata:block_timestat', 'block_timestat')],
                (object) ['block_timestat' => $grouped[$context->id]]
            );
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        $records = timestat::get_records(['contextid' => $context->id]);
        foreach ($records as $record) {
            $record->delete();
        }
    }

    /**
     * Delete data for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        $allowed = array_map('intval', $contextlist->get_contextids());

        $records = timestat::get_records(['userid' => $userid]);
        foreach ($records as $record) {
            if (!in_array((int) $record->get('contextid'), $allowed, true)) {
                continue;
            }
            $record->delete();
        }
    }
}
