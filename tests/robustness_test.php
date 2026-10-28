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
 * Robustness and configuration: operands with special characters, the plugin settings, the editor configuration.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\comparator
 * @covers     \availability_usercriteria\frontend
 */
final class robustness_test extends auc_testcase {
    /**
     * Text operands are compared literally: SQL wildcards, quotes and backslashes have no special meaning.
     */
    public function test_special_characters_are_literal(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cases = [
            // City of the person, rule, expected.
            ['abc', ['op' => 'contains', 'val' => '%'], false],
            ['50% off', ['op' => 'contains', 'val' => '%'], true],
            ['xay', ['op' => 'contains', 'val' => '_'], false],
            ['x_y', ['op' => 'contains', 'val' => '_'], true],
            ["O'Brien", ['op' => 'eq', 'val' => "O'Brien"], true],
            ["O'Brien", ['op' => 'startswith', 'val' => "O'"], true],
            ['a\\b', ['op' => 'endswith', 'val' => '\\b'], true],
            ['ab', ['op' => 'endswith', 'val' => '\\b'], false],
            ['x', ['op' => 'eq', 'val' => "x' OR '1'='1"], false],
        ];
        foreach ($cases as $i => [$city, $rule, $expected]) {
            $user = $this->getDataGenerator()->create_user(['city' => $city]);
            $this->assertSame(
                $expected,
                $this->met(['feature' => 'user_city'] + $rule, $course, $user),
                "Case $i: " . json_encode([$city, $rule])
            );
        }
    }

    /**
     * The participant roles are a plugin setting, by default the student archetype.
     */
    public function test_setting(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $page = \admin_get_root(true, true)->locate('availabilitysettingusercriteria');
        $this->assertNotNull($page, 'Settings page of the plugin');
        $setting = $page->settings->{'availability_usercriteriaparticipantroles'} ?? null;
        $this->assertInstanceOf(\admin_setting_pickroles::class, $setting);
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->assertSame([$student => 1], $setting->get_defaultsetting());
    }

    /**
     * The editor gets the relative operators for exactly the comparable characteristics, and the new hints.
     */
    public function test_editor_configuration(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $frontend = new frontend();
        $method = new \ReflectionMethod($frontend, 'get_javascript_init_params');
        [$config] = $method->invoke($frontend, $course, null, null);
        $relative = array_column($config['operators']['relative'], 'kind', 'op');
        $this->assertSame(['best' => 'count', 'worst' => 'count', 'max' => 'none', 'min' => 'none',
            'toppct' => 'percent', 'bottompct' => 'percent'], $relative);
        $flags = array_column($config['features'], 'relative', 'key');
        $this->assertEqualsCanonicalizing(['course_grade', 'completed_count', 'usage_time'], array_keys(array_filter($flags)));
        $this->assertArrayHasKey('usage_time', $config['hints']);
        $this->assertArrayHasKey('completion_duration', $config['hints']);
        $this->assertNotEmpty($config['labels']['relativeinfo']);
    }
}
