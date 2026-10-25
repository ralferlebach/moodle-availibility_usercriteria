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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/auc_testcase.php');

use availability_usercriteria\local\catalog;
use availability_usercriteria\local\reader;

/**
 * Tests for the condition: structure, storage and evaluation against real Moodle data.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\condition
 * @covers     \availability_usercriteria\local\reader
 * @covers     \availability_usercriteria\local\catalog
 */
final class condition_test extends \availability_usercriteria\auc_testcase {
    /**
     * Malformed structures are rejected when the form is saved.
     *
     * @dataProvider invalid_structures
     * @param array $structure Structure.
     */
    public function test_invalid_structure(array $structure): void {
        $this->expectException(\coding_exception::class);
        $this->cond($structure);
    }

    /**
     * Malformed structures.
     *
     * @return array
     */
    public static function invalid_structures(): array {
        return [
            'no feature' => [['op' => 'gte', 'val' => 1]],
            'bad feature' => [['feature' => 'a b', 'op' => 'gte', 'val' => 1]],
            'unknown operator' => [['feature' => 'course_grade', 'op' => 'regex', 'val' => 1]],
            'no value' => [['feature' => 'course_grade', 'op' => 'gte']],
            'string number' => [['feature' => 'course_grade', 'op' => 'gte', 'val' => 'x']],
            'backward range' => [['feature' => 'course_grade', 'op' => 'between', 'val' => 9, 'val2' => 1]],
            'zero days' => [['feature' => 'site_lastlogin', 'op' => 'inlast', 'val' => 0]],
            'empty list' => [['feature' => 'role_system', 'op' => 'in', 'val' => []]],
            'empty needle' => [['feature' => 'user_username', 'op' => 'contains', 'val' => ' ']],
            'future schema' => [['v' => 2, 'feature' => 'course_grade', 'op' => 'missing']],
        ];
    }

    /**
     * Saving and reloading loses nothing.
     */
    public function test_save_roundtrip(): void {
        $structures = [
            ['feature' => 'course_grade', 'op' => 'between', 'val' => 10, 'val2' => 90.5],
            ['feature' => 'role_course_effective', 'op' => 'in', 'val' => ['3', '5']],
            ['feature' => 'site_lastlogin', 'op' => 'missing'],
            ['feature' => 'user_username', 'op' => 'startswith', 'val' => 'abc'],
        ];
        foreach ($structures as $structure) {
            $cond = $this->cond($structure);
            $saved = $cond->save();
            $again = new condition($saved);
            $this->assertEquals($saved, $again->save());
            $this->assertSame('usercriteria', $saved->type);
            $this->assertSame(1, $saved->v);
        }
    }

    /**
     * Course grade: normalised, ungraded is missing and not zero, negation never releases undecidable data.
     */
    public function test_course_grade(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $graded = $generator->create_and_enrol($course, 'student');
        $ungraded = $generator->create_and_enrol($course, 'student');
        $this->grade_user($course, $graded, 60);
        $info = $this->info($course, $graded->id);

        $atleast50 = $this->cond(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50]);
        $this->assertTrue($atleast50->is_available(false, $info, true, $graded->id));
        $this->assertFalse($atleast50->is_available(true, $info, true, $graded->id));

        // Ungraded: not met, and not met under NOT either - missing data never releases.
        $this->assertFalse($atleast50->is_available(false, $info, true, $ungraded->id));
        $this->assertFalse($atleast50->is_available(true, $info, true, $ungraded->id));
        $this->assertTrue($this->cond(['feature' => 'course_grade', 'op' => 'missing'])
            ->is_available(false, $info, true, $ungraded->id));
        $this->assertFalse($this->cond(['feature' => 'course_grade', 'op' => 'lt', 'val' => 1])
            ->is_available(false, $info, true, $ungraded->id), 'No grade is not a grade of zero');
    }

    /**
     * A course total that still needs a regrade cannot be decided and is never released.
     */
    public function test_course_grade_needs_update(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $user, 80);
        $info = $this->info($course, $user->id);
        $cond = $this->cond(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        $DB->set_field('grade_items', 'needsupdate', 1, ['courseid' => $course->id, 'itemtype' => 'course']);
        reader::reset();
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
    }

    /**
     * Enrolment method and start with several enrolments: at least one active enrolment must match.
     */
    public function test_enrolment(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student', 'manual');
        $info = $this->info($course, $user->id);

        $manual = $this->cond(['feature' => 'enrol_method', 'op' => 'in', 'val' => ['manual']]);
        $self = $this->cond(['feature' => 'enrol_method', 'op' => 'in', 'val' => ['self']]);
        $this->assertTrue($manual->is_available(false, $info, true, $user->id));
        $this->assertFalse($self->is_available(false, $info, true, $user->id));

        // Suspend the manual enrolment: no active enrolment is left.
        $ue = $DB->get_record('user_enrolments', ['userid' => $user->id]);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['id' => $ue->id]);
        reader::reset();
        $this->assertFalse($manual->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'enrol_start', 'op' => 'missing'])
            ->is_available(false, $info, true, $user->id));
    }

    /**
     * Enrolment start prefers the validity start over the creation time.
     */
    public function test_enrolment_start(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $start = $clock->time() - 10 * DAYSECS;
        $generator->enrol_user($user->id, $course->id, 'student', 'manual', $start);
        $info = $this->info($course, $user->id);

        $after = $this->cond(['feature' => 'enrol_start', 'op' => 'before', 'val' => $start + 60]);
        $this->assertTrue($after->is_available(false, $info, true, $user->id));
        $within = $this->cond(['feature' => 'enrol_start', 'op' => 'inlast', 'val' => 5]);
        $this->assertFalse($within->is_available(false, $info, true, $user->id));
    }

    /**
     * Direct and effective roles are separate, and the system role is read from the system context.
     */
    public function test_roles(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        // Role inherited from the category context.
        role_assign($managerrole, $user->id, \context_coursecat::instance($category->id)->id);
        $info = $this->info($course, $user->id);

        $directmanager = $this->cond(['feature' => 'role_course_direct', 'op' => 'in', 'val' => [(string) $managerrole]]);
        $effectivemanager = $this->cond(['feature' => 'role_course_effective', 'op' => 'in',
            'val' => [(string) $managerrole]]);
        $directstudent = $this->cond(['feature' => 'role_course_direct', 'op' => 'in', 'val' => [(string) $studentrole]]);
        $this->assertFalse($directmanager->is_available(false, $info, true, $user->id));
        $this->assertTrue($effectivemanager->is_available(false, $info, true, $user->id));
        $this->assertTrue($directstudent->is_available(false, $info, true, $user->id));

        $system = $this->cond(['feature' => 'role_system', 'op' => 'in', 'val' => [(string) $managerrole]]);
        $this->assertFalse($system->is_available(false, $info, true, $user->id));
        role_assign($managerrole, $user->id, \context_system::instance()->id);
        reader::reset();
        $this->assertTrue($system->is_available(false, $info, true, $user->id));
    }

    /**
     * Completion counts only completed, visible activities with tracking.
     */
    public function test_completed_count(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_and_enrol($course, 'student');
        $info = $this->info($course, $user->id);
        $options = ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL];
        $page1 = $generator->create_module('page', $options);
        $page2 = $generator->create_module('page', $options);
        $hidden = $generator->create_module('page', $options + ['visible' => 0]);
        $completion = new \completion_info($course);
        foreach ([$page1, $page2, $hidden] as $page) {
            $cm = get_coursemodule_from_id('page', $page->cmid);
            $completion->update_state($cm, COMPLETION_COMPLETE, $user->id);
        }

        $two = $this->cond(['feature' => 'completed_count', 'op' => 'eq', 'val' => 2]);
        $this->assertTrue($two->is_available(false, $info, true, $user->id), 'The hidden activity is not counted');
        $three = $this->cond(['feature' => 'completed_count', 'op' => 'gte', 'val' => 3]);
        $this->assertFalse($three->is_available(false, $info, true, $user->id));
    }

    /**
     * Without completion tracking in the course the count cannot be decided.
     */
    public function test_completed_count_without_tracking(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $zero = $this->cond(['feature' => 'completed_count', 'op' => 'lt', 'val' => 1]);
        $this->assertFalse($zero->is_available(false, $this->info($course, $user->id), true, $user->id));
        $this->assertFalse($zero->is_available(true, $this->info($course, $user->id), true, $user->id));
    }

    /**
     * Identity and standard profile fields.
     */
    public function test_identity_and_profile(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['username' => 'ralf.test', 'firstname' => 'Ralf', 'city' => 'Wuppertal',
            'department' => '', 'email' => 'ralf@uni-test.example', 'phone1' => '']);
        $info = $this->info($course, $user->id);

        $this->assertTrue($this->cond(['feature' => 'user_username', 'op' => 'startswith', 'val' => 'RALF'])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'user_id', 'op' => 'eq', 'val' => (int) $user->id])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'user_city', 'op' => 'eq', 'val' => 'wuppertal'])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'user_department', 'op' => 'missing'])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'user_email', 'op' => 'endswith', 'val' => '@uni-test.example'])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'user_phone1', 'op' => 'missing'])
            ->is_available(false, $info, true, $user->id));
        $this->assertFalse($this->cond(['feature' => 'user_department', 'op' => 'ne', 'val' => 'x'])
            ->is_available(true, $info, true, $user->id), 'Missing data under NOT is still not released');
    }

    /**
     * Custom profile fields: text, menu, checkbox, and a deleted field fails closed.
     */
    public function test_custom_profile_fields(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'cohort', 'name' => 'Cohort']);
        $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'level', 'name' => 'Level',
            'param1' => "A\nB\nC"]);
        $generator->create_custom_profile_field(['datatype' => 'checkbox', 'shortname' => 'consent', 'name' => 'Consent']);
        $course = $generator->create_course();
        $user = $generator->create_user(['profile_field_cohort' => 'WS2026', 'profile_field_level' => 'B',
            'profile_field_consent' => 0]);
        $info = $this->info($course, $user->id);

        $this->assertFalse($this->cond(['feature' => 'profile:cohort', 'op' => 'contains', 'val' => 'ws26'])
            ->is_available(false, $info, true, $user->id), 'WS2026 does not contain ws26');
        $this->assertTrue($this->cond(['feature' => 'profile:cohort', 'op' => 'endswith', 'val' => '2026'])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'profile:level', 'op' => 'in', 'val' => ['B', 'C']])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'profile:consent', 'op' => 'in', 'val' => ['0']])
            ->is_available(false, $info, true, $user->id));

        $DB->delete_records('user_info_field', ['shortname' => 'cohort']);
        catalog::reset();
        $this->assertFalse($this->cond(['feature' => 'profile:cohort', 'op' => 'missing'])
            ->is_available(false, $info, true, $user->id), 'A deleted field is undecidable');
        $this->assertFalse($this->cond(['feature' => 'profile:cohort', 'op' => 'missing'])
            ->is_available(true, $info, true, $user->id));
    }

    /**
     * Site login and last course access, including "never".
     */
    public function test_login_and_course_access(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $info = $this->info($course, $user->id);

        $this->assertTrue($this->cond(['feature' => 'site_lastlogin', 'op' => 'missing'])
            ->is_available(false, $info, true, $user->id));
        $this->assertTrue($this->cond(['feature' => 'course_lastaccess', 'op' => 'missing'])
            ->is_available(false, $info, true, $user->id));

        $DB->set_field('user', 'lastlogin', $clock->time() - 10 * DAYSECS, ['id' => $user->id]);
        $DB->set_field('user', 'currentlogin', $clock->time() - 2 * DAYSECS, ['id' => $user->id]);
        $DB->insert_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $course->id,
            'timeaccess' => $clock->time() - 30 * DAYSECS]);
        reader::reset();
        $this->assertTrue($this->cond(['feature' => 'site_lastlogin', 'op' => 'inlast', 'val' => 3])
            ->is_available(false, $info, true, $user->id), 'The latest login counts');
        $this->assertTrue($this->cond(['feature' => 'course_lastaccess', 'op' => 'olderthan', 'val' => 14])
            ->is_available(false, $info, true, $user->id));
    }

    /**
     * Descriptions never contain values of the person, and identity criteria are not spelled out.
     */
    public function test_description(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['username' => 'secretname']);
        $info = $this->info($course, $user->id);

        $grade = $this->cond(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50]);
        $text = $grade->get_description(true, false, $info);
        $this->assertStringContainsString('50', $text);
        $this->assertStringContainsString('NOT', $grade->get_description(true, true, $info));

        $identity = $this->cond(['feature' => 'user_username', 'op' => 'eq', 'val' => 'secretname']);
        $this->assertStringNotContainsString('secretname', $identity->get_description(true, false, $info));
        $mail = $this->cond(['feature' => 'user_email', 'op' => 'endswith', 'val' => '@private.example']);
        $this->assertStringNotContainsString('private.example', $mail->get_description(true, false, $info));
    }

    /**
     * Evaluating the same condition for many activities reads the data once per request, not once per activity.
     */
    public function test_reads_are_cached_per_request(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->grade_user($course, $user, 70);
        $info = $this->info($course, $user->id);
        $cond = $this->cond(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50]);
        reader::reset();

        $before = $DB->perf_get_reads();
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        }
        $this->assertLessThanOrEqual(2, $DB->perf_get_reads() - $before, 'One read for 50 evaluations, not 50');
    }

    /**
     * The catalog offers only operators that match the type, and every feature has strings.
     */
    public function test_catalog_consistency(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'x1', 'name' => 'X1']);
        $operators = catalog::operators();
        foreach (catalog::all() as $key => $def) {
            $this->assertArrayHasKey($def['type'], $operators, $key);
            if (!isset($def['label'])) {
                $this->assertTrue(get_string_manager()->string_exists('feature_' . $key, 'availability_usercriteria'), $key);
            }
        }
        foreach (catalog::all_operators() as $operator) {
            $this->assertTrue(get_string_manager()->string_exists('op_' . $operator, 'availability_usercriteria'), $operator);
        }
    }

    /**
     * The frontend delivers a complete configuration for the editor.
     */
    public function test_frontend_params(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $frontend = new frontend();
        $method = new \ReflectionMethod($frontend, 'get_javascript_init_params');
        $params = $method->invoke($frontend, $course);
        $config = $params[0];
        $this->assertNotEmpty($config['features']);
        $this->assertArrayHasKey('number', $config['operators']);
        $this->assertNotEmpty($config['options']['role_course_direct']);
        $this->assertNotEmpty($config['options']['enrol_method']);
        $this->assertSame(\core_date::get_user_timezone(), $config['tz']);
        $this->assertNotEmpty($config['bounds']);
    }
}
