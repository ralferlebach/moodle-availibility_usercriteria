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

use availability_usercriteria\local\value;

/**
 * Reads the last access to a course from the core last-access table.
 *
 * The characteristic is "last course access", not "last learning activity": the last time Moodle recorded the
 * person in any page of the course (an activity page counts, as Moodle records the course of every page it
 * serves), at most once per Moodle's update interval. It is deliberately not a log query: the table has one
 * row per person and course, is indexed, and does not depend on log retention or on which log store is
 * enabled. Entries in the logs are ignored.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity {
    /**
     * Read the last course access.
     *
     * @param int $userid User id.
     * @param \stdClass $course The course.
     * @return value
     */
    public static function read(int $userid, \stdClass $course): value {
        global $DB;
        if ((int) $course->id === SITEID) {
            return value::unavailable();
        }
        $time = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $userid, 'courseid' => $course->id]);
        return $time ? value::of((int) $time) : value::missing();
    }
}
