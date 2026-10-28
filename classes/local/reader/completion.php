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
 * Counts completed course activities.
 *
 * Two counts exist, chosen by the characteristic:
 * - completed_count: successfully completed - state complete or complete with pass.
 * - finished_count: finished - additionally including complete with fail ("not passed").
 *
 * Which activities count is decided by Moodle's course module information, without the viewer: activities with
 * completion tracking, visible (the visibility the teacher set) and not being deleted. Availability restrictions
 * of an activity are deliberately not evaluated here: an evaluation of "is the activity accessible for this
 * person" would itself evaluate this plugin and could loop when the restricted activity is counted by its own
 * rule. The states of the person are read with one query over exactly those activities.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion {
    /** @var int Largest number of activities in one IN list, for portability between databases. */
    private const CHUNK = 1000;

    /**
     * Read the number of completed or finished activities.
     *
     * @param string $key completed_count or finished_count.
     * @param int $userid User id.
     * @param \stdClass $course The course.
     * @return value
     */
    public static function read(string $key, int $userid, \stdClass $course): value {
        return self::read_many($key, [$userid], $course)[$userid];
    }

    /**
     * Read the number of completed or finished activities of several people.
     *
     * @param string $key completed_count or finished_count.
     * @param int[] $userids User ids.
     * @param \stdClass $course The course.
     * @return value[] User id => value.
     */
    public static function read_many(string $key, array $userids, \stdClass $course): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        if ((int) $course->id === SITEID || !(new \completion_info($course))->is_enabled()) {
            return array_fill_keys($userids, value::unavailable());
        }
        $cmids = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->completion != COMPLETION_TRACKING_NONE && $cm->visible && !$cm->deletioninprogress) {
                $cmids[] = (int) $cm->id;
            }
        }
        $states = [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS];
        if ($key === 'finished_count') {
            $states[] = COMPLETION_COMPLETE_FAIL;
        }
        $counts = array_fill_keys($userids, 0);
        foreach (array_chunk($userids, self::CHUNK) as $users) {
            foreach (array_chunk($cmids, self::CHUNK) as $chunk) {
                [$usersql, $userparams] = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED, 'u');
                [$cminsql, $cmparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'cm');
                [$statesql, $stateparams] = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED, 'st');
                $rows = $DB->get_records_sql_menu(
                    "SELECT cmc.userid, COUNT(1)
                       FROM {course_modules_completion} cmc
                      WHERE cmc.userid $usersql AND cmc.coursemoduleid $cminsql AND cmc.completionstate $statesql
                   GROUP BY cmc.userid",
                    $userparams + $cmparams + $stateparams
                );
                foreach ($rows as $userid => $count) {
                    $counts[(int) $userid] += (int) $count;
                }
            }
        }
        return array_map(fn($count) => value::of($count), $counts);
    }
}
