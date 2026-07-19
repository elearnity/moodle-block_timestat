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

namespace block_timestat\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
use block_timestat\reportbuilder\local\entities\timestat;

/**
 * Course dedication datasource.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_dedication extends datasource {

    /**
     * Return user friendly name of the report source.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('coursededication', 'block_timestat');
    }

    /**
     * Initialise report.
     */
    protected function initialise(): void {
        $timestatentity = new timestat();
        $timestatalias = $timestatentity->get_table_alias('block_timestat');

        $this->set_main_table('block_timestat', $timestatalias);
        $this->add_entity($timestatentity);

        // Join the user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity->add_join(
            "LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$timestatalias}.userid"
        ));

        // Join the course entity.
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity->add_join(
            "LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$timestatalias}.courseid"
        ));

        $this->add_all_from_entities();
    }

    /**
     * Return the columns that will be added to the report upon creation.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'user:fullname',
            'course:fullname',
            'timestat:activityname',
            'timestat:timespent',
            'timestat:timecreated',
        ];
    }

    /**
     * Return the column sorting that will be added to the report upon creation.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'user:fullname' => SORT_ASC,
            'timestat:timespent' => SORT_DESC,
        ];
    }

    /**
     * Return the filters that will be added to the report upon creation.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'course:courseselector',
            'user:fullname',
            'timestat:timecreated',
            'timestat:timespent',
        ];
    }

    /**
     * Return the conditions that will be added to the report upon creation.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'course:courseselector',
        ];
    }
}
