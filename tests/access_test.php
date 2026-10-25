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
 * Tests of the real access decisions in Moodle's rule tree: AND, OR, NOT, sections, direct access, roles, missing
 * data and invalid rules. These check whether an activity is accessible, not only what a message says.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\condition
 */
final class access_test extends auc_testcase {
    /**
     * Whether a person may access an activity, as Moodle decides it when a page is rendered.
     *
     * @param \stdClass $course Course.
     * @param int $cmid Course module id.
     * @param \stdClass $user User.
     * @return bool
     */
    private function accessible(\stdClass $course, int $cmid, \stdClass $user): bool {
        reader::reset();
        return get_fast_modinfo($course, $user->id)->get_cm($cmid)->uservisible;
    }

    /**
     * Set up a course with a page, a student without grade and a student with grade 80.
     *
     * @return array Course, page, ungraded student, graded student, teacher.
     */
    private function scene(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $ungraded = $generator->create_and_enrol($course, 'student');
        $graded = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->grade_user($course, $graded, 80);
        return [$course, $page, $ungraded, $graded, $teacher];
    }

    /**
     * A restricted activity is accessible for those who meet the rule only.
     */
    public function test_activity_access(): void {
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded, $teacher] = $this->scene();
        $this->restrict($page->cmid, $course, [['feature' => 'course_grade', 'op' => 'gte', 'val' => 50]]);

        $this->assertTrue($this->accessible($course, $page->cmid, $graded));
        $this->assertFalse($this->accessible($course, $page->cmid, $ungraded), 'No grade: no access');
        $this->assertTrue($this->accessible($course, $page->cmid, $teacher), 'Teachers may ignore restrictions');
    }

    /**
     * Direct access by URL is refused the same way.
     */
    public function test_direct_access_is_refused(): void {
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded, $teacher] = $this->scene();
        $this->restrict($page->cmid, $course, [['feature' => 'course_grade', 'op' => 'gte', 'val' => 50]]);
        $cm = get_coursemodule_from_id('page', $page->cmid, $course->id, false, MUST_EXIST);

        $this->setUser($graded);
        require_login($course, false, $cm, false, true);
        $this->assertTrue(true, 'The graded student gets in');

        reader::reset();
        $this->setUser($ungraded);
        $this->expectException(\require_login_exception::class);
        require_login($course, false, $cm, false, true);
    }

    /**
     * AND, OR and NOT of Moodle's own tree combine the conditions correctly, including missing data.
     */
    public function test_combinations(): void {
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded] = $this->scene();
        $high = ['feature' => 'course_grade', 'op' => 'gte', 'val' => 50];
        $present = ['feature' => 'course_grade', 'op' => 'present'];
        $missing = ['feature' => 'course_grade', 'op' => 'missing'];
        $never = ['feature' => 'course_grade', 'op' => 'gte', 'val' => 101];

        $cases = [
            // Root, conditions, expected for the graded person (80 percent), expected for the ungraded person.
            'AND all met' => ['&', [$high, $present], true, false],
            'AND one not met' => ['&', [$high, $never], false, false],
            'OR one met' => ['|', [$never, $present], true, false],
            'OR none met' => ['|', [$never, $never], false, false],
            'OR with the explicit missing operator' => ['|', [$high, $missing], true, true],
            // The ungraded person: "has a grade" is decidable (no), so NOT of it holds; the grade comparison
            // is not decidable.
            'NOT AND (not all met)' => ['!&', [$high, $present], false, true],
            'NOT AND with one unmet' => ['!&', [$high, $never], true, false],
            'NOT OR (none met)' => ['!|', [$never, $never], true, false],
            'NOT OR with one met' => ['!|', [$high, $never], false, false],
            'NOT of the explicit missing' => ['!|', [$missing], true, false],
        ];
        foreach ($cases as $name => [$root, $conditions, $expectedgraded, $expectedungraded]) {
            $this->restrict($page->cmid, $course, $conditions, $root);
            $this->assertSame($expectedgraded, $this->accessible($course, $page->cmid, $graded), "$name: graded");
            $this->assertSame($expectedungraded, $this->accessible($course, $page->cmid, $ungraded), "$name: ungraded");
        }
    }

    /**
     * Missing data never releases an activity, not even under NOT.
     */
    public function test_negation_does_not_release_missing_data(): void {
        $this->resetAfterTest();
        [$course, $page, $ungraded] = $this->scene();
        $stranger = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($stranger->id, $course->id, 'student');
        foreach (['!&', '!|'] as $root) {
            foreach (
                [
                ['feature' => 'course_grade', 'op' => 'gte', 'val' => 50],
                ['feature' => 'course_grade', 'op' => 'lt', 'val' => 50],
                ['feature' => 'profile:doesnotexist', 'op' => 'eq', 'val' => 'x'],
                ['feature' => 'user_department', 'op' => 'ne', 'val' => 'x'],
                ['feature' => 'site_lastlogin', 'op' => 'before', 'val' => 4102444800],
                ] as $condition
            ) {
                $this->restrict($page->cmid, $course, [$condition], $root);
                $this->assertFalse($this->accessible($course, $page->cmid, $ungraded), $root . ' ' . json_encode($condition));
            }
        }
    }

    /**
     * Restricting a section works like restricting an activity.
     */
    public function test_section_access(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded] = $this->scene();
        $sectionid = get_fast_modinfo($course)->get_cm($page->cmid)->section;
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'id' => $sectionid]);
        $grade = ['type' => 'usercriteria', 'v' => 1, 'feature' => 'course_grade', 'op' => 'gte', 'val' => 50];
        $tree = ['op' => '&', 'c' => [$grade], 'showc' => [false]];
        $DB->set_field('course_sections', 'availability', json_encode($tree), ['id' => $section->id]);
        rebuild_course_cache($course->id, true);

        reader::reset();
        $this->assertTrue(get_fast_modinfo($course, $graded->id)->get_section_info_by_id($section->id)->uservisible);
        reader::reset();
        $this->assertFalse(get_fast_modinfo($course, $ungraded->id)->get_section_info_by_id($section->id)->uservisible);
    }

    /**
     * The condition can be combined with Moodle's own conditions.
     */
    public function test_combined_with_core_conditions(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded] = $this->scene();
        // Moodle's own user profile condition (the city) together with this plugin's course grade.
        $DB->set_field('user', 'city', 'Wuppertal', ['id' => $graded->id]);
        $DB->set_field('user', 'city', 'Wuppertal', ['id' => $ungraded->id]);
        $city = (object) ['type' => 'profile', 'sf' => 'city', 'op' => 'isequalto', 'v' => 'Wuppertal'];
        $grade = (object) ['type' => 'usercriteria', 'v' => 1, 'feature' => 'course_grade', 'op' => 'gte', 'val' => 50];
        $DB->set_field(
            'course_modules',
            'availability',
            json_encode(\core_availability\tree::get_root_json([$city, $grade])),
            ['id' => $page->cmid]
        );
        rebuild_course_cache($course->id, true);
        $this->assertTrue($this->accessible($course, $page->cmid, $graded), 'Both conditions are met');
        $this->assertFalse($this->accessible($course, $page->cmid, $ungraded), 'The city fits, the grade is missing');
        $DB->set_field('user', 'city', 'Berlin', ['id' => $graded->id]);
        $this->assertFalse($this->accessible($course, $page->cmid, $graded), 'The grade is enough, the city does not fit');
    }

    /**
     * Roles: direct and effective, for teacher and student.
     */
    public function test_roles(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded, $teacher] = $this->scene();
        $teacherrole = (string) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $this->restrict($page->cmid, $course, [['feature' => 'role_course_direct', 'op' => 'in', 'val' => [$teacherrole]]]);
        $this->assertFalse($this->accessible($course, $page->cmid, $graded));
        $this->assertTrue($this->accessible($course, $page->cmid, $teacher));
    }

    /**
     * A rule that does not fit the site any more does not release the activity.
     */
    public function test_invalid_rules_do_not_release(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded] = $this->scene();
        $bad = [
            'unknown operator' => ['feature' => 'course_grade', 'op' => 'regex', 'val' => 1],
            'unknown feature' => ['feature' => 'nothing_here', 'op' => 'present'],
            'wrong operand type' => ['feature' => 'course_grade', 'op' => 'gte', 'val' => 'x'],
            'operator that does not fit the type' => ['feature' => 'course_grade', 'op' => 'contains', 'val' => 'x'],
            'future schema' => ['v' => 99, 'feature' => 'course_grade', 'op' => 'present'],
        ];
        // A rule that can not even be loaded: Moodle reports it to developers and denies access. A rule that loads
        // but does not fit the site (unknown feature, operator of the wrong type) is not met.
        foreach ($bad as $name => $condition) {
            $tree = ['op' => '&', 'c' => [$condition + ['type' => 'usercriteria', 'v' => 1]], 'showc' => [true]];
            $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $page->cmid]);
            rebuild_course_cache($course->id, true);
            $this->assertFalse($this->accessible($course, $page->cmid, $graded), $name);
            $this->assertFalse($this->accessible($course, $page->cmid, $ungraded), $name);
            // Core reports rules it can not load to developers; whether it does is core's business, not asserted.
            $this->resetDebugging();
        }
    }

    /**
     * Rules saved by version 0.1.0 still load, evaluate and save unchanged.
     */
    public function test_rules_of_version_0_1_0(): void {
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded] = $this->scene();
        $old = '{"op":"&","c":[{"type":"usercriteria","v":1,"feature":"course_grade","op":"between",'
            . '"val":70,"val2":80}],"showc":[true]}';
        $condition = new condition(json_decode($old)->c[0]);
        $this->assertEquals(json_decode($old)->c[0], $condition->save(), 'Saved again without any change');
        // The upper limit was included in 0.1.0 and still is by default.
        $between = ['feature' => 'course_grade', 'op' => 'between', 'val' => 70, 'val2' => 80];
        $this->assertTrue($this->met($between, $course, $graded));
        $this->assertFalse($this->met($between + ['inc' => 'lower'], $course, $graded));
    }

    /**
     * Teachers and students see no values of other people through the restriction text.
     */
    public function test_restriction_text_shows_no_values_of_people(): void {
        $this->resetAfterTest();
        [$course, $page, $ungraded, $graded, $teacher] = $this->scene();
        $this->restrict($page->cmid, $course, [['feature' => 'user_username', 'op' => 'eq', 'val' => $graded->username]]);
        reader::reset();
        $info = get_fast_modinfo($course, $ungraded->id)->get_cm($page->cmid)->availableinfo;
        $this->assertStringNotContainsString($graded->username, (string) $info);
        $this->assertStringNotContainsString($ungraded->username, (string) $info);
    }
}
