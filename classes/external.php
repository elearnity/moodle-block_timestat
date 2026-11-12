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
 * This is the external API for this component.
 *
 * @package    block_timestat
 * @copyright  2022 Jorge C. {}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_timestat;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/blocks/timestat/locallib.php');

use context;
use core_course\external\course_summary_exporter;
use dml_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use invalid_parameter_exception;
use moodle_exception;

/**
 * This is the external API for this component.
 *
 * @copyright  2020 Mathew May {@link https://mathew.solutions}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * update_register_parameters
     *
     * @return external_function_parameters
     */
    public static function update_register_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                        'timespent' => new external_value(PARAM_INT),
                        'contextid' => new external_value(PARAM_INT),
                ]
        );
    }

    /**
     *
     * Update the register to save the timespent in a specific log.
     *
     * @param int $timespent The user time spent
     * @param int $contextid The log id
     * @return array
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function update_register(int $timespent, int $contextid): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::update_register_parameters(),
            ['timespent' => $timespent, 'contextid' => $contextid]
        );
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        $log = block_timestat_get_user_last_log_by_contextid($params['contextid']);
        if ($log === null) {
            return [];
        }
        if ((int)$log->userid !== (int)$USER->id) {
            throw new moodle_exception('nopermissions', 'error', '', 'update timestat');
        }

        $maximum = max(0, time() - (int)$log->timecreated);
        $validatedtimespent = min(max(0, $params['timespent']), $maximum);
        $recordtimestat = $DB->get_record('block_timestat', ['log_id' => $log->id]);

        if (!$recordtimestat) {
            $recordbt = new \stdClass();
            $recordbt->log_id = $log->id;
            $recordbt->timespent = $validatedtimespent;
            try {
                $DB->insert_record('block_timestat', $recordbt);
            } catch (\dml_write_exception $exception) {
                // A concurrent heartbeat may have inserted the unique log row first.
                $recordtimestat = $DB->get_record('block_timestat', ['log_id' => $log->id]);
                if (!$recordtimestat) {
                    throw $exception;
                }
                $recordtimestat->timespent = max((int)$recordtimestat->timespent, $validatedtimespent);
                $DB->update_record('block_timestat', $recordtimestat);
            }
            return [];
        }
        $recordtimestat->timespent = max((int)$recordtimestat->timespent, $validatedtimespent);
        $DB->update_record('block_timestat', $recordtimestat);
        return [];
    }

    /**
     * update_register_returns.
     *
     * @return \external_description
     */
    public static function update_register_returns() {
        return new external_single_structure([]);
    }
}
