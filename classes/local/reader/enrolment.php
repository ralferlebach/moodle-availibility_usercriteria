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
 * Reads enrolment method and enrolment start over the person's active enrolments.
 *
 * Definition of "active enrolment" at the moment of evaluation (all must hold):
 * - the enrolment and its enrolment instance are enabled (not suspended),
 * - it has started: its start is not in the future,
 * - it has not ended: no end time, or the end lies in the future.
 *
 * The start of an enrolment is its validity start. With no validity start (0) the enrolment applies from its
 * creation, so the creation time is the start; a creation time in the future (inconsistent data) means it has
 * not started yet.
 *
 * With several enrolments a criterion is met if at least one active enrolment meets it. A person without any
 * active enrolment has no value at all (missing): "is one of" and "is none of" can not be decided for them and
 * are not met, so "not enrolled via method X" never releases someone who is not enrolled at all. Ask for that
 * explicitly with "is not available".
 *
 * Only the raw enrolment rows are kept in the request cache. Whether a row is active depends on the time and
 * is worked out on every evaluation, so a time limit that passes during one request is honoured.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment {
    /**
     * Read enrolment data.
     *
     * @param string $key enrol_method or enrol_start.
     * @param int $userid User id.
     * @param \stdClass $course The course.
     * @param int $now Current time, taken from the clock by the caller.
     * @return value
     */
    public static function read(string $key, int $userid, \stdClass $course, int $now): value {
        if ((int) $course->id === SITEID) {
            return value::unavailable();
        }
        $methods = [];
        $starts = [];
        foreach (self::rows($userid, (int) $course->id) as $row) {
            if ($row['uestatus'] != ENROL_USER_ACTIVE || $row['estatus'] != ENROL_INSTANCE_ENABLED) {
                continue;
            }
            $start = $row['timestart'] > 0 ? $row['timestart'] : $row['timecreated'];
            if ($start > $now || ($row['timeend'] > 0 && $row['timeend'] <= $now)) {
                continue;
            }
            $methods[$row['enrol']] = $row['enrol'];
            $starts[] = $start;
        }
        if (!$starts) {
            return value::missing();
        }
        return value::of($key === 'enrol_method' ? array_values($methods) : $starts);
    }

    /**
     * All enrolment rows of a person in a course, independent of time.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return array[] Rows with plain integer fields.
     */
    private static function rows(int $userid, int $courseid): array {
        global $DB;
        $cache = \cache::make('availability_usercriteria', 'values');
        $cachekey = 'enrolrows_' . $userid . '_' . $courseid;
        $stored = $cache->get($cachekey);
        if ($stored !== false) {
            return $stored;
        }
        $sql = "SELECT ue.id, e.enrol, e.status AS estatus, ue.status AS uestatus,
                       ue.timestart, ue.timeend, ue.timecreated
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.courseid = :courseid";
        $rows = [];
        foreach ($DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]) as $record) {
            $rows[] = [
                'enrol' => (string) $record->enrol,
                'estatus' => (int) $record->estatus,
                'uestatus' => (int) $record->uestatus,
                'timestart' => (int) $record->timestart,
                'timeend' => (int) $record->timeend,
                'timecreated' => (int) $record->timecreated,
            ];
        }
        $cache->set($cachekey, $rows);
        return $rows;
    }
}
