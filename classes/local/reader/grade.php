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
 * Reads the course total, normalised to 0-100 percent.
 *
 * Follows the core grade availability condition: the final grade (which already includes teacher overrides) is
 * normalised with the grade range stored with the grade, the range of the course total item being the fallback.
 * Hidden and locked grades are used as they are; only a verdict leaves the plugin, never the grade.
 *
 * A scale is turned into a percentage of its range (first item 0 %, last item 100 %), as core does.
 *
 * Not decidable (unavailable): no course total, a total of type text or none, and an empty range. An ungraded
 * person is missing, never zero. The percentage is not rounded: a rounded 49.999996 must not satisfy
 * "at least 50".
 *
 * Deliberate differences to the core grade condition, both fail closed:
 * - A course total that still needs a regrade (needsupdate) is not decidable; core uses the stored, possibly
 *   outdated value. The restriction stays closed until Moodle has recalculated the grades.
 * - Under NOT a missing grade does not release; core's "NOT grade at least x" releases ungraded people.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade {
    /**
     * Read the normalised course total.
     *
     * @param int $userid User id.
     * @param \stdClass $course The course.
     * @return value
     */
    public static function read(int $userid, \stdClass $course): value {
        global $DB;
        $sql = "SELECT gi.id, gi.gradetype, gi.grademin, gi.grademax, gi.needsupdate,
                       gg.finalgrade, gg.rawgrademin, gg.rawgrademax
                  FROM {grade_items} gi
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.courseid = :courseid AND gi.itemtype = :itemtype";
        $row = $DB->get_record_sql($sql, [
            'userid' => $userid,
            'courseid' => $course->id,
            'itemtype' => 'course',
        ]);
        if (!$row || !empty($row->needsupdate) || !in_array((int) $row->gradetype, [GRADE_TYPE_VALUE, GRADE_TYPE_SCALE], true)) {
            return value::unavailable();
        }
        if ($row->finalgrade === null) {
            return value::missing();
        }
        $min = $row->rawgrademin;
        $max = $row->rawgrademax;
        if ($min === null || $max === null || (float) $max <= (float) $min) {
            $min = $row->grademin;
            $max = $row->grademax;
        }
        $range = (float) $max - (float) $min;
        if ($range <= 0) {
            return value::unavailable();
        }
        return value::of(((float) $row->finalgrade - (float) $min) / $range * 100);
    }
}
