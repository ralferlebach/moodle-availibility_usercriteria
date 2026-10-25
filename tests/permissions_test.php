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
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Who can configure the conditions, who is subject to them, and what the editor reveals.
 *
 * The plugin adds no capability of its own. Configuring is limited by Moodle: only people who may edit an
 * activity (moodle/course:manageactivities) or a section (moodle/course:update) reach the restriction editor.
 * People with moodle/course:ignoreavailabilityrestrictions are not restricted. This is the same model as core's
 * profile condition (availability_profile), which offers the same identity fields. See docs/Berechtigungen.md.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\frontend
 * @covers     \availability_usercriteria\condition
 */
final class permissions_test extends auc_testcase {
    /**
     * The permission matrix for the standard roles, against a restriction nobody meets.
     */
    public function test_matrix(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->restrict($page->cmid, $course, [['feature' => 'course_grade', 'op' => 'gte', 'val' => 101]]);
        $coursecontext = \context_course::instance($course->id);
        $modulecontext = \context_module::instance($page->cmid);

        $people = [];
        foreach (['editingteacher', 'teacher', 'student'] as $role) {
            $people[$role] = $this->getDataGenerator()->create_and_enrol($course, $role);
        }
        $people['manager'] = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'manager']),
            $people['manager']->id,
            $coursecontext->id
        );
        $people['admin'] = get_admin();

        // Role => [edit activity restrictions, edit section restrictions, restricted by the rule].
        $expected = [
            'editingteacher' => [true, true, false],
            'teacher' => [false, false, false],
            'manager' => [true, true, false],
            'admin' => [true, true, false],
            'student' => [false, false, true],
        ];
        foreach ($expected as $role => [$editactivity, $editsection, $restricted]) {
            $user = $people[$role];
            $this->assertSame(
                $editactivity,
                has_capability('moodle/course:manageactivities', $modulecontext, $user),
                "$role: edit activity restrictions"
            );
            $this->assertSame(
                $editsection,
                has_capability('moodle/course:update', $coursecontext, $user),
                "$role: edit section restrictions"
            );
            get_fast_modinfo($course, 0, true);
            $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
            $this->assertSame(!$restricted, $cm->uservisible, "$role: can open the activity");
            // The rule is evaluated for everybody; people allowed to ignore restrictions are let in anyway.
            $this->assertFalse($cm->available, "$role: the rule itself is not met");
        }
    }

    /**
     * The editor offers field definitions and role and method names, never data of people.
     */
    public function test_editor_reveals_no_data_of_people(): void {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'secretfield',
            'name' => 'Team', 'visible' => PROFILE_VISIBLE_NONE]);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'level',
            'name' => 'Level', 'param1' => "Beginner\nExpert"]);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Hildegard', 'lastname' => 'Geheimnis', 'email' => 'hildegard.geheimnis@example.org',
            'city' => 'Geheimstadt', 'idnumber' => 'ID-778899', 'phone1' => '0202-998877',
            'profile_field_secretfield' => 'Geheimwert', 'profile_field_level' => 'Expert',
        ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $frontend = new frontend();
        $method = new \ReflectionMethod($frontend, 'get_javascript_init_params');
        $json = json_encode($method->invoke($frontend, $course, null, null), JSON_UNESCAPED_UNICODE);
        $private = ['Hildegard', 'Geheimnis', 'Geheimstadt', 'ID-778899', '0202-998877', 'Geheimwert', $student->id . '"'];
        foreach ($private as $value) {
            $this->assertStringNotContainsString($value, $json);
        }
        // Definitions are offered: the field names and the menu options set up by the administrator.
        $this->assertStringContainsString('Team', $json);
        $this->assertStringContainsString('Expert', $json);
        $this->assertEmpty($DB->get_records('user_preferences', ['userid' => $teacher->id, 'name' => 'availability_usercriteria']));
    }

    /**
     * Hidden custom profile fields are offered like in core's profile condition; the editor never shows values.
     */
    public function test_hidden_profile_fields_like_core(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'hiddenone',
            'name' => 'Hidden one', 'visible' => PROFILE_VISIBLE_NONE]);
        \availability_usercriteria\local\catalog::reset();
        $this->assertNotNull(\availability_usercriteria\local\catalog::get('profile:hiddenone'));
        $core = array_map(fn($field) => $field->shortname, profile_get_custom_fields(true));
        $this->assertContains('hiddenone', $core, 'Core offers hidden fields as well');
    }
}
