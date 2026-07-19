# Timestat block for Moodle

This block measure time of real activity done by Moodle users.

## Installation

Install block in standard way (copy it to '/moodle/blocks' folder and click 'Notifications' in admin panel) or install it directly from the Admin panel.

## Usage

The block only counts the time on the pages to which it has been added, so you need to add the block on the pages where you want to count the time. If you want to add the block on the course page and on all activity pages at once, please refer to the following documentation:
https://docs.moodle.org/400/en/Block_settings#Making_a_block_sticky_throughout_a_course

The block accounts for student *inactivity*, identified by no interactions such as clicks or scrolling. To prevent counting time during periods of inactivity, the tracking feature automatically pauses when a student is inactive for an extended period. You can customize the maximum inactivity time in the settings. It's also possible to adjust how often the recorded time is saved.

The block offers a *visual time counter*, visible to users with specific permissions (block/timestat:viewtimer) or to all users enrolled in the course, if enabled in the settings. *Time is tracked even if the block or counter isn't visible to a student*. Additionally, the block includes a link to a detailed *report* on time spent, with filters for course, activity, and user. Initially, only roles such as editing teachers, teachers, course creators, managers, and admins can access this report. The 'block/timestat:viewreport' capability allows extending access to other roles.

To use the block within the *Quiz attempt page*, configure the quiz settings to 'Show blocks during the attempt' by going to Quiz > Edit Settings > Appearance > Show more.

You can access the plugin *settings* from *Site Administration > Plugins > Blocks > Timestat*.

## Public API

Other plugins (e.g. reports) can query dedication time via `\block_timestat\api`.
Callers must perform their own capability checks.

```php
use block_timestat\api;

$courseseconds = api::get_course_timespent($courseid, $userid);
$activityseconds = api::get_activity_timespent($cmid, $userid);
$contextseconds = api::get_context_timespent($contextid, $userid);
```

## More information

The version of the plugin for Moodle 2.9 and earlier was developed by:
Barbara Dębska
Łukasz Musiał
Łukasz Sanokowski

Upgrade from 1.9 to 2.5 version was made thanks to contribution of:
Classroom Revolution
Lib Ertea
Mart van der Niet
Joseph Thibault

## License

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html).
