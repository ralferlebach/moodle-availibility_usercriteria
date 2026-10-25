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

use availability_usercriteria\local\catalog;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/auc_testcase.php');

/**
 * Tests for custom profile fields: types, the opt-in numeric reading, empty values and unknown fields.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\reader\profile
 * @covers     \availability_usercriteria\local\catalog
 */
final class profile_test extends auc_testcase {
    /**
     * Free text offers text operators; a second, explicit characteristic offers numbers.
     */
    public function test_numeric_characteristic_is_an_explicit_opt_in(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'points', 'name' => 'Points']);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'tier', 'name' => 'Tier',
            'param1' => "A\nB"]);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'datetime', 'shortname' => 'born', 'name' => 'Born']);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'checkbox', 'shortname' => 'ok', 'name' => 'Ok']);
        $all = catalog::all();
        $this->assertSame('text', $all['profile:points']['type']);
        $this->assertSame('number', $all['profile_num:points']['type']);
        $this->assertArrayNotHasKey('profile_num:tier', $all);
        $this->assertArrayNotHasKey('profile_num:born', $all);
        $this->assertArrayNotHasKey('profile_num:ok', $all);
        $this->assertSame('select', $all['profile:tier']['type']);
        $this->assertSame('time', $all['profile:born']['type']);
        $this->assertSame('select', $all['profile:ok']['type']);
    }

    /**
     * Values: whole numbers, decimals with dot and comma, negative, empty, invalid text.
     *
     * @return array
     */
    public static function numbers(): array {
        return [
            'whole number' => ['42', ['gte', 40], true],
            'whole number below' => ['42', ['gte', 43], false],
            'decimal with dot' => ['3.5', ['gt', 3], true],
            'decimal with comma' => ['3,5', ['lt', 4], true],
            'decimal exact' => ['3.5', ['eq', 3.5], true],
            'negative' => ['-2', ['lt', 0], true],
            'between' => ['10', ['between', 5, 10], true],
            'leading and trailing blanks' => ['  7 ', ['eq', 7], true],
            'empty is missing' => ['', ['missing'], true],
            'empty never satisfies a comparison' => ['', ['gte', 0], false],
            'invalid text is not decidable' => ['about ten', ['gte', 0], false],
            'invalid text is not "not available" either' => ['about ten', ['missing'], false],
            'invalid text under NOT is not released' => ['about ten', ['gte', 0, 'not'], false],
            'text with unit is not a number' => ['10 km', ['eq', 10], false],
            'scientific notation is not accepted' => ['1e3', ['eq', 1000], false],
        ];
    }

    /**
     * Numeric comparison of a text field.
     *
     * @dataProvider numbers
     * @param string $stored Stored text.
     * @param array $rule Operator and operands; 'not' as last entry negates.
     * @param bool $expected Expected result.
     */
    public function test_numeric_values(string $stored, array $rule, bool $expected): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'points', 'name' => 'Points']);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['profile_field_points' => $stored]);
        $not = end($rule) === 'not';
        if ($not) {
            array_pop($rule);
        }
        $structure = ['feature' => 'profile_num:points', 'op' => $rule[0]];
        if (isset($rule[1])) {
            $structure['val'] = $rule[1];
        }
        if (isset($rule[2])) {
            $structure['val2'] = $rule[2];
        }
        $this->assertSame($expected, $this->met($structure, $course, $user, $not));
    }

    /**
     * The same field as text keeps working as text.
     */
    public function test_text_reading_is_unchanged(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'points', 'name' => 'Points']);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['profile_field_points' => '42']);
        $this->assertTrue($this->met(['feature' => 'profile:points', 'op' => 'startswith', 'val' => '4'], $course, $user));
        $this->assertFalse($this->met(['feature' => 'profile:points', 'op' => 'eq', 'val' => '042'], $course, $user));
    }

    /**
     * Date fields, with the clock frozen.
     */
    public function test_datetime_field(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'datetime', 'shortname' => 'since', 'name' => 'Since',
            'param1' => 2000, 'param2' => 2100]);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['profile_field_since' => $clock->time() - 10 * DAYSECS]);
        $none = $this->getDataGenerator()->create_user();
        $this->assertTrue($this->met(['feature' => 'profile:since', 'op' => 'olderthan', 'val' => 5], $course, $user));
        $this->assertFalse($this->met(['feature' => 'profile:since', 'op' => 'inlast', 'val' => 5], $course, $user));
        $this->assertTrue($this->met(['feature' => 'profile:since', 'op' => 'missing'], $course, $none));
    }

    /**
     * An unknown or deleted field is not decidable.
     */
    public function test_unknown_field(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        foreach (['profile:nothere', 'profile_num:nothere'] as $feature) {
            $this->assertFalse($this->met(['feature' => $feature, 'op' => 'present'], $course, $user));
            $this->assertFalse($this->met(['feature' => $feature, 'op' => 'present'], $course, $user, true));
        }
    }

    /**
     * The custom fields are read once per request, not once per evaluated condition.
     */
    public function test_fields_are_read_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'points', 'name' => 'Points']);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['profile_field_points' => '1']);
        catalog::reset();
        $before = $DB->perf_get_reads();
        for ($i = 0; $i < 30; $i++) {
            $this->assertNotNull(catalog::get('profile:points'));
        }
        $this->assertSame(1, $DB->perf_get_reads() - $before);
        $this->assertTrue($user->id > 0 && $course->id > 0);
    }
}
