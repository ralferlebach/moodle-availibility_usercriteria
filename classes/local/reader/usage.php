<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace availability_usercriteria\local\reader;

use availability_usercriteria\local\usage as usage_data;
use availability_usercriteria\local\value;

/**
 * Reads the estimated usage time (L-09) and the time from the first logged access to course completion (L-10).
 *
 * Both come from the table the scheduled task fills from the standard log (see local\usage for the method). They
 * are as current as the last run of the task (every 10 minutes by default).
 *
 * - usage_time: hours, as a decimal number. A person without any logged event since the log has been processed
 *   has 0 hours.
 * - completion_duration: days, as a decimal number, from the first logged access to the course to the time the
 *   course was completed. Not completed (yet), or no logged access before the completion: missing.
 *
 * Not decidable (unavailable): standard log store off, task never run, site course; for the completion duration
 * also course completion off or without criteria.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /**
     * Read usage time or completion duration.
     *
     * @param string $key usage_time or completion_duration.
     * @param int $userid User id.
     * @param \stdClass $course The course.
     * @return value
     */
    public static function read(string $key, int $userid, \stdClass $course): value {
        global $CFG, $DB;
        if ((int) $course->id === SITEID || !usage_data::is_tracking()) {
            return value::unavailable();
        }
        $row = usage_data::row($userid, (int) $course->id);
        if ($key === 'usage_time') {
            return value::of($row ? (int) $row->seconds / HOURSECS : 0.0);
        }
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($course);
        if (!$completion->is_enabled() || !$completion->has_criteria()) {
            return value::unavailable();
        }
        $completed = (int) $DB->get_field('course_completions', 'timecompleted', ['userid' => $userid, 'course' => $course->id]);
        if (!$completed || !$row || (int) $row->firstaccess === 0 || $completed < (int) $row->firstaccess) {
            return value::missing();
        }
        return value::of(($completed - (int) $row->firstaccess) / DAYSECS);
    }
}
