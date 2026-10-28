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

namespace availability_usercriteria\local;

/**
 * The participants of a course: the reference population of relative criteria (L-11).
 *
 * A participant has an active enrolment in the course (same rule as for the enrolment characteristics) and a
 * role assigned directly in the course that the administrator chose as a participant role (plugin setting
 * "participantroles"; default: the roles with the archetype "student").
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class population {
    /**
     * The participant role ids from the setting; never set: the roles with the archetype student.
     *
     * @return int[]
     */
    public static function role_ids(): array {
        $setting = get_config('availability_usercriteria', 'participantroles');
        if ($setting === false) {
            return array_map(fn($role) => (int) $role->id, array_values(get_archetype_roles('student')));
        }
        return array_values(array_filter(array_map('intval', explode(',', (string) $setting))));
    }

    /**
     * Ids of the participants of a course.
     *
     * @param int $courseid Course id.
     * @param int $now Current time.
     * @return int[]
     */
    public static function participants(int $courseid, int $now): array {
        global $DB;
        $roles = self::role_ids();
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$roles || !$context) {
            return [];
        }
        [$rolesql, $params] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'r');
        $sql = "SELECT DISTINCT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = :contextid
                 WHERE e.courseid = :courseid AND e.status = :instanceenabled AND ue.status = :active
                       AND u.deleted = 0 AND ra.roleid $rolesql
                       AND ((ue.timestart > 0 AND ue.timestart <= :now1) OR (ue.timestart = 0 AND ue.timecreated <= :now2))
                       AND (ue.timeend = 0 OR ue.timeend > :now3)";
        $params += ['contextid' => $context->id, 'courseid' => $courseid, 'instanceenabled' => ENROL_INSTANCE_ENABLED,
            'active' => ENROL_USER_ACTIVE, 'now1' => $now, 'now2' => $now, 'now3' => $now];
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Whether one person is a participant of a course now.
     *
     * @param int $userid User id.
     * @param \stdClass $course Course.
     * @param int $now Current time.
     * @return bool
     */
    public static function is_participant(int $userid, \stdClass $course, int $now): bool {
        $enrolment = reader\enrolment::read('enrol_method', $userid, $course, $now);
        if ($enrolment->is_unavailable() || $enrolment->is_absent()) {
            return false;
        }
        // Through the request cache: a page with many relative rules reads the roles once.
        $direct = reader::read(catalog::get('role_course_direct'), $userid, $course, $now);
        if ($direct->is_unavailable() || $direct->is_absent()) {
            return false;
        }
        return (bool) array_intersect(array_map('intval', $direct->get()), self::role_ids());
    }
}
