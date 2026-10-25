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
 * Reads role assignments. Directly assigned and effective roles are separate characteristics.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role {
    /**
     * Read the role ids of a person.
     *
     * @param string $key role_course_direct, role_course_effective or role_system.
     * @param int $userid User id.
     * @param \stdClass $course The course.
     * @return value A list of role ids; an empty list means no such role.
     */
    public static function read(string $key, int $userid, \stdClass $course): value {
        if ($key === 'role_system') {
            $roles = get_user_roles(\context_system::instance(), $userid, false);
        } else {
            if ((int) $course->id === SITEID) {
                return value::unavailable();
            }
            $context = \context_course::instance($course->id, IGNORE_MISSING);
            if (!$context) {
                return value::unavailable();
            }
            // Effective roles include those inherited from category or system context.
            $roles = get_user_roles($context, $userid, $key === 'role_course_effective');
        }
        $ids = [];
        foreach ($roles as $role) {
            $ids[(int) $role->roleid] = (string) (int) $role->roleid;
        }
        return value::of(array_values($ids));
    }
}
