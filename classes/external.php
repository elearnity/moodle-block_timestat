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
 * External API for block_timestat.
 *
 * @package    block_timestat
 * @copyright  2022 Jorge C. {}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_timestat;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use block_timestat\external\timestat_exporter;
use block_timestat\local\manager;
use block_timestat\local\timestat;
use context;
use external_api;
use external_description;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use moodle_exception;

/**
 * External API for block_timestat.
 *
 * @package    block_timestat
 * @copyright  2020 Mathew May {@link https://mathew.solutions}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Parameters for update_register.
     *
     * @return external_function_parameters
     */
    public static function update_register_parameters(): external_function_parameters {
        return new external_function_parameters([
            'timespent' => new external_value(PARAM_INT),
            'contextid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Update the register to save the timespent for the latest log in a context.
     *
     * @param int $timespent The user time spent
     * @param int $contextid The context id
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function update_register(int $timespent, int $contextid): array {
        global $OUTPUT, $USER;

        $context = context::instance_by_id($contextid);
        self::validate_context($context);
        $params = self::validate_parameters(
            self::update_register_parameters(),
            ['timespent' => $timespent, 'contextid' => $contextid]
        );

        $log = manager::get_user_last_log_by_contextid($params['contextid']);
        if ((int) $log->userid !== (int) $USER->id) {
            throw new moodle_exception('nopermissions', 'error', '', get_string('update'));
        }

        $record = timestat::upsert_from_log($log, (int) $params['timespent']);
        $exporter = new timestat_exporter($record, ['context' => $context]);

        return (array) $exporter->export($OUTPUT);
    }

    /**
     * Return structure for update_register.
     *
     * @return external_description
     */
    public static function update_register_returns(): external_description {
        return timestat_exporter::get_read_structure();
    }
}
