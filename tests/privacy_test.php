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

/**
 * Tests for the privacy declaration and for what leaves the plugin: descriptions, debug output.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\privacy\provider
 * @covers     \availability_usercriteria\condition
 */
final class privacy_test extends auc_testcase {
    /**
     * The plugin declares that it has no storage of its own, and the text says what rules can contain.
     */
    public function test_declaration(): void {
        $this->assertTrue(is_a(privacy\provider::class, \core_privacy\local\metadata\null_provider::class, true));
        $reason = get_string(privacy\provider::get_reason(), 'availability_usercriteria');
        $this->assertStringContainsString('no tables of its own', $reason);
        $this->assertStringContainsString('identifying values', $reason);
        $this->assertStringContainsString('backups', $reason);
        $string = [];
        include(__DIR__ . '/../lang/de/availability_usercriteria.php');
        $this->assertStringContainsString('sicherungen', $string[privacy\provider::get_reason()]);
    }

    /**
     * The plugin has no tables of its own.
     */
    public function test_no_tables(): void {
        $this->assertFileDoesNotExist(__DIR__ . '/../db/install.xml');
    }

    /**
     * Identifying and contact operands appear neither in the text nor in the debug output.
     */
    public function test_operands_of_people_are_not_disclosed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $info = $this->info($course, $user->id);
        $secrets = [
            'user_id' => 31337,
            'user_username' => 'secret.person',
            'user_firstname' => 'Secretfirst',
            'user_lastname' => 'Secretlast',
            'user_email' => 'secret@private.example',
            'user_phone1' => '0123456789',
            'user_phone2' => '0987654321',
            'user_address' => 'Secretstreet 1',
            'user_middlename' => 'Secretmiddle',
            'user_alternatename' => 'Secretalt',
        ];
        foreach ($secrets as $feature => $secret) {
            $operator = is_int($secret) ? 'eq' : 'contains';
            $condition = $this->cond(['feature' => $feature, 'op' => $operator, 'val' => $secret]);
            foreach ([true, false] as $not) {
                $this->assertStringNotContainsString((string) $secret, $condition->get_description(true, $not, $info), $feature);
                $this->assertStringNotContainsString((string) $secret, $condition->get_description(false, $not, $info), $feature);
            }
            $this->assertStringNotContainsString((string) $secret, (string) $condition, $feature . ' debug');
        }
    }

    /**
     * Operands that are no identification (a grade threshold) stay visible: they are the rule, not a person.
     */
    public function test_thresholds_remain_visible(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $text = $this->cond(['feature' => 'course_grade', 'op' => 'gte', 'val' => 55])
            ->get_description(true, false, $this->info($course, $user->id));
        $this->assertStringContainsString('55', $text);
    }

    /**
     * A person named in a rule: the privacy API exports and deletes nothing for this plugin, and the rule stays
     * course configuration after the person is deleted (like core's profile condition). See docs/Datenschutz.md.
     */
    public function test_person_named_in_a_rule(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $named = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'named.person@example.org']);
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->restrict($page->cmid, $course, [['feature' => 'user_email', 'op' => 'eq', 'val' => 'named.person@example.org']]);

        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('availability_usercriteria'));
        $contexts = (new \core_privacy\manager())->get_contexts_for_userid($named->id);
        foreach ($contexts as $contextlist) {
            $this->assertNotSame('availability_usercriteria', $contextlist->get_component());
        }

        // The restriction text for another person does not reveal the address.
        $cm = get_fast_modinfo($course, $other->id)->get_cm($page->cmid);
        $text = \core_availability\info::format_info($cm->availableinfo, $course);
        $this->assertStringNotContainsString('named.person', $text);

        // Deleting the person does not change course configuration: the address stays in the rule.
        delete_user($named);
        $this->assertStringContainsString(
            'named.person@example.org',
            $DB->get_field('course_modules', 'availability', ['id' => $page->cmid])
        );
    }

    /**
     * Error messages for malformed rules contain no values of the rule.
     */
    public function test_error_messages_contain_no_values(): void {
        $bad = [
            ['feature' => 'user_email', 'op' => 'between', 'val' => 'secret.value@example.org'],
            ['feature' => 'user_email', 'op' => 'eq', 'val' => ['secret.value@example.org']],
            ['feature' => 'user_username', 'op' => 'in', 'val' => 'secret.value'],
            ['feature' => 'user_email', 'op' => 'secret.value', 'val' => 'x'],
        ];
        foreach ($bad as $structure) {
            try {
                $this->cond($structure);
                $this->fail('Malformed rule accepted: ' . json_encode($structure));
            } catch (\coding_exception $e) {
                $this->assertStringNotContainsString('secret', $e->getMessage());
                $this->assertStringNotContainsString('secret', (string) $e->debuginfo);
            }
        }
    }
}
