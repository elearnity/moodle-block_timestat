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

namespace block_timestat\external;

use block_timestat\local\timestat;
use core\external\persistent_exporter;
use renderer_base;

/**
 * Exporter for timestat persistent records.
 *
 * @package    block_timestat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timestat_exporter extends persistent_exporter {

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class(): string {
        return timestat::class;
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'timespentformatted' => [
                'type' => PARAM_TEXT,
            ],
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        $timespent = (int) ($this->persistent->get('timespent') ?? 0);

        return [
            'timespentformatted' => $timespent > 0 ? format_time($timespent) : format_time(0),
        ];
    }

    /**
     * Returns a list of objects that are related to this persistent.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
        ];
    }
}
