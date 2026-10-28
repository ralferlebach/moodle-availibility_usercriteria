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
     * The declaration lists the one table with personal data, with every column that refers to a person.
     */
    public function test_declaration(): void {
        $this->assertTrue(is_a(privacy\provider::class, \core_privacy\local\metadata\provider::class, true));
        $this->assertTrue(is_a(privacy\provider::class, \core_privacy\local\request\core_userlist_provider::class, true));
        $collection = privacy\provider::get_metadata(new \core_privacy\local\metadata\collection('availability_usercriteria'));
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame('availability_usercriteria_use', $items[0]->get_name());
        $this->assertSame(
            ['userid', 'courseid', 'seconds', 'sessions', 'firstaccess', 'lastevent', 'timemodified'],
            array_keys($items[0]->get_privacy_fields())
        );
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('availability_usercriteria'));
    }

    /**
     * Only the usage table refers to persons; the lists for relative criteria hold values only.
     */
    public function test_tables(): void {
        $xml = simplexml_load_file(__DIR__ . '/../db/install.xml');
        $tables = [];
        foreach ($xml->TABLES->TABLE as $table) {
            $fields = [];
            foreach ($table->FIELDS->FIELD as $field) {
                $fields[] = (string) $field['NAME'];
            }
            $tables[(string) $table['NAME']] = $fields;
        }
        $this->assertSame(['availability_usercriteria_use', 'availability_usercriteria_dist'], array_keys($tables));
        $this->assertContains('userid', $tables['availability_usercriteria_use']);
        $this->assertNotContains('userid', $tables['availability_usercriteria_dist']);
    }

    /**
     * Export and deletion of the usage data through the privacy API.
     */
    public function test_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        foreach ([[$user, $course], [$user, $other], [$second, $course]] as [$person, $where]) {
            $DB->insert_record('availability_usercriteria_use', (object) ['userid' => $person->id, 'courseid' => $where->id,
                'seconds' => 7200, 'sessions' => 3, 'firstaccess' => 1000000000, 'lastevent' => 1000003600,
                'timemodified' => 1000003600]);
        }
        $context = \context_course::instance($course->id);

        $contexts = privacy\provider::get_contexts_for_userid($user->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$context->id, \context_course::instance($other->id)->id], $contexts);
        $userlist = new \core_privacy\local\request\userlist($context, 'availability_usercriteria');
        privacy\provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user->id, $second->id], $userlist->get_userids());

        $approved = new \core_privacy\local\request\approved_contextlist($user, 'availability_usercriteria', [$context->id]);
        privacy\provider::export_user_data($approved);
        $writer = \core_privacy\local\request\writer::with_context($context);
        $data = $writer->get_data([get_string('privacy:path', 'availability_usercriteria')]);
        $this->assertEquals(2.0, $data->usagehours);
        $this->assertSame(3, $data->sessions);

        privacy\provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('availability_usercriteria_use', ['userid' => $user->id, 'courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('availability_usercriteria_use', ['userid' => $user->id, 'courseid' => $other->id]));

        privacy\provider::delete_data_for_users(new \core_privacy\local\request\approved_userlist(
            $context,
            'availability_usercriteria',
            [$second->id]
        ));
        $this->assertSame(0, $DB->count_records('availability_usercriteria_use', ['courseid' => $course->id]));

        privacy\provider::delete_data_for_all_users_in_context(\context_course::instance($other->id));
        $this->assertSame(0, $DB->count_records('availability_usercriteria_use'));
    }

    /**
     * Deleting a course or a person removes their stored data.
     */
    public function test_deleted_course_and_person(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $kept = $this->getDataGenerator()->create_user();
        foreach ([$user, $kept] as $person) {
            $DB->insert_record('availability_usercriteria_use', (object) ['userid' => $person->id, 'courseid' => $course->id,
                'seconds' => 60, 'sessions' => 1, 'firstaccess' => 1, 'lastevent' => 61, 'timemodified' => 61]);
        }
        $DB->insert_record('availability_usercriteria_dist', (object) ['courseid' => $course->id, 'metric' => 'course_grade',
            'vals' => '[1,2]', 'population' => 2, 'timecomputed' => 1]);
        delete_user($user);
        $this->assertSame(1, $DB->count_records('availability_usercriteria_use'));
        delete_course($course, false);
        $this->assertSame(0, $DB->count_records('availability_usercriteria_use'));
        $this->assertSame(0, $DB->count_records('availability_usercriteria_dist'));
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
