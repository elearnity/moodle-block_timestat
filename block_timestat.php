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
 * Contains the class for the timestat block.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

use block_timestat\api;

/**
 * Timestat block class.
 *
 * @package    block_timestat
 * @copyright  2014 Barbara Dębska, Łukasz Sanokowski, Łukasz Musiał
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_timestat extends block_base {

    /**
     * Initialises the block.
     *
     * @return void
     * @throws coding_exception
     */
    public function init() {
        $this->title = get_string('blocktitle', 'block_timestat');
    }

    /**
     * Returns the contents.
     *
     * @return stdClass contents of block
     * @throws dml_exception
     */
    public function get_content() {
        global $COURSE, $OUTPUT, $USER;
        if ($this->content !== null) {
            return $this->content;
        }

        $modulecontext = $this->get_current_module_context();
        $contextid = $modulecontext ? $modulecontext->id : $this->page->context->id;
        $blockcontext = context_block::instance($this->instance->id);
        $userisenrolled = is_enrolled($blockcontext);
        $config = get_config('block_timestat');
        $this->content = new stdClass();
        $this->content->text = '';
        $canseetimer = has_capability('block/timestat:viewtimer', $blockcontext);
        $courseseconds = api::get_course_timespent((int) $COURSE->id, (int) $USER->id);
        $isinactivity = $modulecontext !== null;

        $data = [
            'courseid' => $COURSE->id,
            'shouldseetimer' => $userisenrolled && ($canseetimer || ($config->showtimer ?? false)),
            'isinactivity' => $isinactivity,
            // On activity pages show activity time large and course total small; otherwise course total large.
            'initialseconds' => $isinactivity
                ? api::get_context_timespent($contextid, (int) $USER->id)
                : $courseseconds,
            'courseseconds' => $courseseconds,
            'shouldseereport' => has_capability('block/timestat:viewreport', $blockcontext),
        ];
        $this->content->text = $OUTPUT->render_from_template('block_timestat/main', $data);
        // If the user is not enrolled in the course, we don't want to count the time.
        if ($userisenrolled) {
            $this->page->requires->js_call_amd('block_timestat/event_emiiter', 'init', [$contextid, $config]);
        }
        return $this->content;
    }

    /**
     * Resolve the current activity/resource module context, if any.
     *
     * Uses $PAGE->cm when available, otherwise falls back to a module page context.
     *
     * @return context_module|null
     */
    protected function get_current_module_context(): ?context_module {
        if (!empty($this->page->cm)) {
            return context_module::instance($this->page->cm->id);
        }

        if ($this->page->context instanceof context_module) {
            return $this->page->context;
        }

        return null;
    }

    /**
     * Defines where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'site-index' => false,
            'course-view' => true,
            'course-view-social' => true,
            'mod' => true,
            'mod-quiz' => true,
            'course' => true,
        ];
    }

    public function has_config() {
        return true;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_config_for_external() {
        $configs = get_config('block_timestat');
        return (object)[
            'instance' => new stdClass(),
            'plugin' => $configs,
        ];
    }
}
