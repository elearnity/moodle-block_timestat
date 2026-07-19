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

namespace block_timestat\reportbuilder\local\entities;

use lang_string;
use stdClass;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, duration, number};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};

/**
 * Timestat entity for Report builder.
 *
 * Uses denormalised fields from the persistent table; no direct logstore joins.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timestat extends base {

    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'block_timestat',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitytimestat', 'block_timestat');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('block_timestat');

        // Time spent.
        $columns[] = (new column(
            'timespent',
            new lang_string('timespent', 'block_timestat'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$alias}.timespent")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'format_time']);

        // Time created.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Activity / resource name.
        $columns[] = (new column(
            'activityname',
            new lang_string('activityname', 'block_timestat'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.contextinstanceid", 'cmid')
            ->add_field("{$alias}.contextlevel", 'contextlevel')
            ->add_field("{$alias}.courseid", 'courseid')
            ->set_is_sortable(false)
            ->add_callback(static function($cmid, stdClass $row): string {
                if ((int) $row->contextlevel !== CONTEXT_MODULE || empty($cmid)) {
                    return get_string('course');
                }
                try {
                    $modinfo = get_fast_modinfo((int) $row->courseid);
                    return $modinfo->get_cm((int) $cmid)->get_formatted_name();
                } catch (\Throwable $e) {
                    return get_string('unknownactivity', 'block_timestat');
                }
            });

        // Course module id.
        $columns[] = (new column(
            'cmid',
            new lang_string('cmid', 'block_timestat'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("CASE WHEN {$alias}.contextlevel = " . CONTEXT_MODULE .
                " THEN {$alias}.contextinstanceid ELSE NULL END", 'cmid')
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Return list of all available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $alias = $this->get_table_alias('block_timestat');

        // Time spent.
        $filters[] = (new filter(
            duration::class,
            'timespent',
            new lang_string('timespent', 'block_timestat'),
            $this->get_entity_name(),
            "{$alias}.timespent"
        ))
            ->add_joins($this->get_joins());

        // Time created.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$alias}.timecreated"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_CURRENT,
                date::DATE_LAST,
                date::DATE_RANGE,
            ]);

        // Course module id.
        $filters[] = (new filter(
            number::class,
            'cmid',
            new lang_string('cmid', 'block_timestat'),
            $this->get_entity_name(),
            "CASE WHEN {$alias}.contextlevel = " . CONTEXT_MODULE .
                " THEN {$alias}.contextinstanceid ELSE NULL END"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
