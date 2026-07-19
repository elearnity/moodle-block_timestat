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
 * Lang strings for the timestat block.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityname'] = 'Activity / resource';
$string['activitytime'] = 'Activity time';
$string['blockname'] = 'Timestat';
$string['blocktitle'] = 'Timestat';
$string['cmid'] = 'Course module ID';
$string['coursededication'] = 'Course dedication time';
$string['entitytimestat'] = 'Dedication time';
$string['inactivitytime'] = 'Inactivity time';
$string['inactivitytime_desc'] = 'The time in seconds after which the user is considered inactive. The minimum value is 10 seconds.';
$string['inactivitytime_small'] = 'Inactivity time (small screens)';
$string['inactivitytime_small_desc'] = 'The time in seconds after which the user is considered inactive when the user\'s activity is logged in small screens. The minimum value is 10 seconds.';
$string['loading'] = 'Loading...';
$string['loginterval'] = 'Log interval (seconds)';
$string['loginterval_desc'] = 'The time interval in which the user\'s activity is logged. The minimum value is 10 seconds.';
$string['loginterval_help'] = 'The time interval in which the user\'s activity is logged.';
$string['nologreader'] = 'No SQL log store is available. Please enable a log store that supports SQL reading.';
$string['nologs'] = 'No dedication time records found.';
$string['pluginname'] = 'Timestat';
$string['privacy:metadata:block_timestat'] = 'Information about the time spent by the user in a specific log entry.';
$string['privacy:metadata:block_timestat:contextid'] = 'The context where the time was spent.';
$string['privacy:metadata:block_timestat:courseid'] = 'The course related to the time spent.';
$string['privacy:metadata:block_timestat:log_id'] = 'The ID of the related log entry.';
$string['privacy:metadata:block_timestat:timespent'] = 'The time spent by the user in the log entry.';
$string['privacy:metadata:block_timestat:userid'] = 'The ID of the user who spent the time.';
$string['reportedtime'] = 'Reported time';
$string['showtimer'] = 'Show timer';
$string['showtimer_desc'] = 'If enabled, the time counter will be visible to all enrolled users. If disabled, the time counter will be visible only to users with the "block/timestat:viewtimer" capability.';
$string['timestat:addinstance'] = 'Add a new Timestat block';
$string['timestat:view'] = 'View the Timestat block';
$string['timestat:viewreport'] = 'View report';
$string['timestat:viewtimer'] = 'View timer';
$string['timespent'] = 'Time spent';
$string['totalcoursetime'] = 'Course total';
$string['unknownactivity'] = 'Unknown activity';
$string['viewreport'] = 'View report';
