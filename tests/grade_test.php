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

/**
 * Tests for the course grade: normalisation, thresholds, overrides, hidden grades, text totals, missing grades.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\reader\grade
 */
final class grade_test extends auc_testcase {
    /**
     * Thresholds just below, on and just above the value; no rounding may move a result.
     */
    public function test_thresholds_are_exact(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $user, 49.99999);
        $ge50 = ['feature' => 'course_grade', 'op' => 'gte', 'val' => 50];
        $this->assertFalse($this->met($ge50, $course, $user), '49.99999 is not at least 50');
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'lt', 'val' => 50], $course, $user));
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 49.99999], $course, $user));
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'gt', 'val' => 49.9999], $course, $user));
    }

    /**
     * The percentage is not rounded: the value 49.999996 stays below 50.
     */
    public function test_percentage_is_not_rounded(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // 14.999999 of 30 is 49.99999666...%, which rounded to five decimals would be 50.00000.
        $this->grade_user($course, $user, 14.99999, 30);
        $value = reader::read(\availability_usercriteria\local\catalog::get('course_grade'), $user->id, $course, 0);
        $this->assertLessThan(50, $value->get());
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50], $course, $user));
    }

    /**
     * Normalisation to percent with different grade ranges.
     */
    public function test_normalisation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $user, 15, 30);
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'eq', 'val' => 50], $course, $user));
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'between', 'val' => 49, 'val2' => 51], $course, $user));
    }

    /**
     * A teacher's override of the course total is what counts.
     */
    public function test_override(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $user, 20);
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 80], $course, $user));

        $item = \grade_item::fetch_course_item($course->id);
        $item->update_final_grade($user->id, 90, 'test', '', FORMAT_MOODLE, null, null, true);
        grade_regrade_final_grades($course->id);
        reader::reset();
        $ge80 = ['feature' => 'course_grade', 'op' => 'gte', 'val' => 80];
        $this->assertTrue($this->met($ge80, $course, $user), 'The override counts');
    }

    /**
     * Hidden and locked grades are used like the core grade condition does; only a verdict is given.
     */
    public function test_hidden_and_locked_grades(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $user, 70);
        $itemid = $DB->get_field('grade_items', 'id', ['courseid' => $course->id, 'itemtype' => 'course']);
        $DB->set_field('grade_grades', 'hidden', 1, ['itemid' => $itemid, 'userid' => $user->id]);
        $DB->set_field(
            'grade_grades',
            'locked',
            \core\di::get(\core\clock::class)->time(),
            ['itemid' => $itemid, 'userid' => $user->id]
        );
        reader::reset();
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 60], $course, $user));

        // The restriction text does not contain the grade of the person.
        $text = $this->cond(['feature' => 'course_grade', 'op' => 'gte', 'val' => 60])
            ->get_description(true, false, $this->info($course, $user->id));
        $this->assertStringNotContainsString('70', $text);
    }

    /**
     * A course total of type text is not decidable, also under NOT.
     */
    public function test_text_total_is_not_decidable(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $user, 70);
        $DB->set_field('grade_items', 'gradetype', GRADE_TYPE_TEXT, ['courseid' => $course->id, 'itemtype' => 'course']);
        reader::reset();
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 1], $course, $user));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 1], $course, $user, true));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'missing'], $course, $user));
    }

    /**
     * Missing grade, missing course total and one person's grade never decide for another person.
     */
    public function test_missing_and_other_users(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $graded = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $ungraded = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $graded, 100);
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 100], $course, $graded));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'gte', 'val' => 0], $course, $ungraded));
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'missing'], $course, $ungraded));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'missing'], $course, $graded));
    }
}
