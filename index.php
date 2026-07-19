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
 * Course dedication system report page.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use block_timestat\reportbuilder\local\systemreports\course_dedication;
use core_reportbuilder\system_report_factory;

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('block/timestat:viewreport', $context);

$PAGE->set_url('/blocks/timestat/index.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('coursededication', 'block_timestat'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('coursededication', 'block_timestat'));

$report = system_report_factory::create(
    course_dedication::class,
    $context,
    '',
    '',
    0,
    ['courseid' => $courseid]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursededication', 'block_timestat'));
echo $report->output();
echo $OUTPUT->footer();
