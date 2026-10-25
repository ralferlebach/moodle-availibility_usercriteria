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

namespace availability_usercriteria;

/**
 * Shared helpers of the plugin's tests.
 *
 * Time is never read from the system in these tests: each test that depends on time freezes the clock with
 * mock_clock_with_frozen() and moves it explicitly.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class auc_testcase extends \advanced_testcase {
    /**
     * Build a condition from an array.
     *
     * @param array $structure Structure properties.
     * @return condition
     */
    protected function cond(array $structure): condition {
        return new condition((object) ($structure + ['type' => 'usercriteria', 'v' => 1]));
    }

    /**
     * Make an info object for a course.
     *
     * @param \stdClass $course Course.
     * @param int $userid User id.
     * @return \core_availability\mock_info
     */
    protected function info(\stdClass $course, int $userid): \core_availability\mock_info {
        global $CFG;
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
        return new \core_availability\mock_info($course, $userid);
    }

    /**
     * Whether a condition is met for a person.
     *
     * @param array $structure Structure properties.
     * @param \stdClass $course Course.
     * @param \stdClass $user User.
     * @param bool $not Whether the condition is negated.
     * @return bool
     */
    protected function met(array $structure, \stdClass $course, \stdClass $user, bool $not = false): bool {
        return $this->cond($structure)->is_available($not, $this->info($course, $user->id), true, $user->id);
    }

    /**
     * Give a user a grade in a manual grade item and bring the course total up to date.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $user User.
     * @param float $grade Grade.
     * @param float $max Largest grade of the item.
     * @return \grade_item The manual item.
     */
    protected function grade_user(\stdClass $course, \stdClass $user, float $grade, float $max = 100): \grade_item {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $item = new \grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Manual',
            'grademax' => $max, 'grademin' => 0], false);
        $item->insert();
        $item->update_final_grade($user->id, $grade);
        grade_regrade_final_grades($course->id);
        return $item;
    }

    /**
     * Restrict a course module with a JSON availability string.
     *
     * @param int $cmid Course module id.
     * @param \stdClass $course Course.
     * @param array $conditions Conditions, each an array of structure properties.
     * @param string $op Operator of the root: & | !& !|.
     */
    protected function restrict(int $cmid, \stdClass $course, array $conditions, string $op = '&'): void {
        global $DB;
        $children = [];
        foreach ($conditions as $condition) {
            $children[] = $condition + ['type' => 'usercriteria', 'v' => 1];
        }
        $tree = ['op' => $op, 'c' => $children];
        // Moodle wants one "show" for OR and NOT AND, and one "showc" per child for AND and NOT OR.
        if ($op === '|' || $op === '!&') {
            $tree['show'] = true;
        } else {
            $tree['showc'] = array_fill(0, count($children), true);
        }
        $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $cmid]);
        rebuild_course_cache($course->id, true);
    }
}
