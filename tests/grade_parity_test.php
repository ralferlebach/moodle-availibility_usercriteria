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

use availability_usercriteria\local\reader;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/auc_testcase.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * The course grade compared with Moodle's own grade condition (availability_grade) on the course total.
 *
 * For every grade situation both conditions are evaluated with the same thresholds: "at least x" against core's
 * minimum, "less than x" against core's (exclusive) maximum. The two deliberate differences are asserted
 * explicitly, not hidden: a total waiting for a regrade, and NOT with a missing grade.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\reader\grade
 */
final class grade_parity_test extends auc_testcase {
    /** @var float[] Thresholds compared. */
    private const THRESHOLDS = [0, 49.99999, 50, 50.00001, 100];

    /**
     * Core's verdict for the course total.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $user User.
     * @param float|null $min Minimum percentage (inclusive).
     * @param float|null $max Maximum percentage (exclusive).
     * @param bool $not Negated.
     * @return bool
     */
    private function core(\stdClass $course, \stdClass $user, ?float $min, ?float $max, bool $not = false): bool {
        \cache::make('availability_grade', 'scores')->purge();
        $item = \grade_item::fetch_course_item($course->id);
        $structure = ['type' => 'grade', 'id' => (int) $item->id];
        if ($min !== null) {
            $structure['min'] = $min;
        }
        if ($max !== null) {
            $structure['max'] = $max;
        }
        $condition = new \availability_grade\condition((object) $structure);
        return $condition->is_available($not, $this->info($course, $user->id), true, $user->id);
    }

    /**
     * Compare both conditions for every threshold.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $user User.
     * @param string $situation Name for messages.
     */
    private function assert_same_verdicts(\stdClass $course, \stdClass $user, string $situation): void {
        reader::reset();
        foreach (self::THRESHOLDS as $x) {
            $this->assertSame(
                $this->core($course, $user, $x, null),
                $this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => $x], $course, $user),
                "$situation: at least $x"
            );
            $this->assertSame(
                $this->core($course, $user, null, $x),
                $this->met(['feature' => 'course_grade', 'op' => 'lt', 'val' => $x], $course, $user),
                "$situation: less than $x"
            );
            $this->assertSame(
                $this->core($course, $user, $x, null, true),
                $this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => $x], $course, $user, true),
                "$situation: NOT at least $x"
            );
        }
    }

    /**
     * A course with one student.
     *
     * @return array Course and user.
     */
    private function scene(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        return [$course, $user];
    }

    /**
     * Grades on and around the thresholds, with different ranges.
     */
    public function test_values_and_ranges(): void {
        $this->resetAfterTest();
        foreach (
            [[0, 100], [50, 100], [100, 100], [49.99999, 100], [50.00001, 100], [10, 20], [7.5, 15],
                [14.99999, 30]] as [$grade, $max]
        ) {
            [$course, $user] = $this->scene();
            $this->grade_user($course, $user, $grade, $max);
            $this->assert_same_verdicts($course, $user, "$grade of $max");
        }
    }

    /**
     * A range that does not start at zero.
     */
    public function test_range_with_minimum(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->scene();
        $item = new \grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'M',
            'grademin' => 10, 'grademax' => 60], false);
        $item->insert();
        $item->update_final_grade($user->id, 35);
        grade_regrade_final_grades($course->id);
        $this->assert_same_verdicts($course, $user, '35 in 10..60');
    }

    /**
     * A teacher's override of the course total, and a changed range followed by the regrade.
     */
    public function test_override_and_changed_range(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->scene();
        $manual = $this->grade_user($course, $user, 20);
        $courseitem = \grade_item::fetch_course_item($course->id);
        $courseitem->update_final_grade($user->id, 90, 'test', '', FORMAT_MOODLE, null, null, true);
        grade_regrade_final_grades($course->id);
        $this->assert_same_verdicts($course, $user, 'override');

        [$course, $user] = $this->scene();
        $manual = $this->grade_user($course, $user, 40);
        $manual->grademax = 50;
        $manual->update();
        grade_regrade_final_grades($course->id);
        $this->assert_same_verdicts($course, $user, 'range changed to 50, regraded');
    }

    /**
     * Hidden and locked grades and a missing grade.
     */
    public function test_hidden_locked_missing(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->scene();
        $this->grade_user($course, $user, 70);
        $itemid = \grade_item::fetch_course_item($course->id)->id;
        $DB->set_field('grade_grades', 'hidden', 1, ['itemid' => $itemid, 'userid' => $user->id]);
        $this->assert_same_verdicts($course, $user, 'hidden');
        $DB->set_field('grade_grades', 'locked', \core\di::get(\core\clock::class)->time(), ['itemid' => $itemid,
            'userid' => $user->id]);
        $this->assert_same_verdicts($course, $user, 'hidden and locked');
        $DB->set_field('grade_items', 'hidden', 1, ['id' => $itemid]);
        $this->assert_same_verdicts($course, $user, 'course total item hidden');

        // Missing grade: equal for the plain conditions; under NOT core releases, this plugin does not.
        [$course, $user] = $this->scene();
        $this->grade_user($course, $this->getDataGenerator()->create_and_enrol($course, 'student'), 80);
        reader::reset();
        foreach (self::THRESHOLDS as $x) {
            $this->assertFalse($this->core($course, $user, $x, null));
            $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => $x], $course, $user));
            $this->assertTrue($this->core($course, $user, $x, null, true), 'Core: NOT releases the ungraded');
            $this->assertFalse(
                $this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => $x], $course, $user, true),
                'Deliberate difference: a missing grade never releases'
            );
        }
    }

    /**
     * A course total of type scale is a percentage of the scale, as in core.
     */
    public function test_scale(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->scene();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'a,b,c,d,e']);
        $this->grade_user($course, $user, 50);
        // A course total of type scale, as stored by the gradebook: the scale position with the range 1..5.
        $courseitem = \grade_item::fetch_course_item($course->id);
        $DB->set_field('grade_items', 'gradetype', GRADE_TYPE_SCALE, ['id' => $courseitem->id]);
        $DB->set_field('grade_items', 'scaleid', $scale->id, ['id' => $courseitem->id]);
        $DB->set_field('grade_items', 'grademin', 1, ['id' => $courseitem->id]);
        $DB->set_field('grade_items', 'grademax', 5, ['id' => $courseitem->id]);
        foreach ([1, 3, 5] as $position) {
            $DB->execute(
                'UPDATE {grade_grades} SET finalgrade = ?, rawgrademin = 1, rawgrademax = 5 WHERE itemid = ? AND userid = ?',
                [$position, $courseitem->id, $user->id]
            );
            $this->assert_same_verdicts($course, $user, "scale position $position of 5");
        }
    }

    /**
     * A course total waiting for a regrade: core uses the stored value, this plugin decides nothing.
     */
    public function test_pending_regrade(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->scene();
        $this->grade_user($course, $user, 80);
        $DB->set_field('grade_items', 'needsupdate', 1, ['courseid' => $course->id, 'itemtype' => 'course']);
        reader::reset();
        $this->assertTrue($this->core($course, $user, 50, null), 'Core uses the stored, possibly outdated total');
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50], $course, $user));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'lt', 'val' => 50], $course, $user));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50], $course, $user, true));

        // After the regrade both agree again.
        grade_regrade_final_grades($course->id);
        $this->assert_same_verdicts($course, $user, 'after the regrade');
    }
}
