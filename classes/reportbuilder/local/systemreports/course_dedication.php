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

namespace block_timestat\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use block_timestat\reportbuilder\local\entities\timestat;
use lang_string;

/**
 * Course dedication system report.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_dedication extends system_report {

    /**
     * Initialise report, set main table, load entities and set columns/filters.
     */
    protected function initialise(): void {
        $timestatentity = new timestat();
        $timestatalias = $timestatentity->get_table_alias('block_timestat');

        $this->set_main_table('block_timestat', $timestatalias);
        $this->add_entity($timestatentity);

        // Restrict to the current course.
        $paramcourseid = database::generate_param_name();
        $courseid = $this->get_parameter('courseid', 0, PARAM_INT);
        $this->add_base_condition_sql("{$timestatalias}.courseid = :{$paramcourseid}", [
            $paramcourseid => $courseid,
        ]);

        // Join the user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity->add_join(
            "LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$timestatalias}.userid"
        ));

        $this->add_columns();
        $this->add_filters();

        $this->set_initial_sort_column('timestat:timespent', SORT_DESC);
        $this->set_default_no_results_notice(new lang_string('nologs', 'block_timestat'));
        $this->set_downloadable(true, get_string('coursededication', 'block_timestat'));
    }

    /**
     * Validates access to view this report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('block/timestat:viewreport', $this->get_context());
    }

    /**
     * Add columns to the report.
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'user:fullname',
            'timestat:activityname',
            'timestat:timespent',
            'timestat:timecreated',
        ]);
    }

    /**
     * Add filters to the report.
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'user:fullname',
            'timestat:cmid',
            'timestat:timecreated',
            'timestat:timespent',
        ]);
    }
}
