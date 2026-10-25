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
 * Proof that every time-dependent characteristic is evaluated with the Moodle clock.
 *
 * The clock is frozen at 9 September 2001, decades away from the real time of any test run, and every value is
 * placed relative to that frozen time. Each assertion therefore has the opposite result if an implementation
 * used the system time instead of the clock; moving the clock then changes the verdict without touching data.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\condition
 * @covers     \availability_usercriteria\local\comparator
 */
final class clock_source_test extends auc_testcase {
    /** @var int The frozen time: 2001-09-09 01:46:40 UTC. */
    private const FROZEN = 1_000_000_000;

    /**
     * Last login, last course access and a date profile field: "within the last day" follows the clock.
     */
    public function test_relative_times_follow_the_clock(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::FROZEN);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'datetime', 'shortname' => 'since',
            'name' => 'Since', 'param1' => 1990, 'param2' => 2100]);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['profile_field_since' => self::FROZEN - 3600]);
        $DB->set_field('user', 'lastlogin', self::FROZEN - 3600, ['id' => $user->id]);
        $DB->set_field('user', 'currentlogin', self::FROZEN - 3600, ['id' => $user->id]);
        $DB->delete_records('user_lastaccess', ['userid' => $user->id, 'courseid' => $course->id]);
        $DB->insert_record('user_lastaccess', (object) ['userid' => $user->id, 'courseid' => $course->id,
            'timeaccess' => self::FROZEN - 3600]);

        foreach (['site_lastlogin', 'course_lastaccess', 'profile:since'] as $feature) {
            reader::reset();
            $this->assertTrue(
                $this->met(['feature' => $feature, 'op' => 'inlast', 'val' => 1], $course, $user),
                "$feature: one hour before the frozen time is within the last day"
            );
            $this->assertFalse($this->met(['feature' => $feature, 'op' => 'olderthan', 'val' => 1], $course, $user), $feature);
        }
        $clock->bump(2 * DAYSECS);
        foreach (['site_lastlogin', 'course_lastaccess', 'profile:since'] as $feature) {
            reader::reset();
            $this->assertFalse(
                $this->met(['feature' => $feature, 'op' => 'inlast', 'val' => 1], $course, $user),
                "$feature: two days later it is not"
            );
            $this->assertTrue($this->met(['feature' => $feature, 'op' => 'olderthan', 'val' => 1], $course, $user), $feature);
        }
    }

    /**
     * An enrolment that starts after the frozen time is not active until the clock reaches its start, and it ends
     * when the clock passes its end; all within one request and without resetting anything.
     */
    public function test_enrolment_window_follows_the_clock(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::FROZEN);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', self::FROZEN + 100, self::FROZEN + 300);
        $in = ['feature' => 'enrol_method', 'op' => 'in', 'val' => ['manual']];
        reader::reset();
        $this->assertFalse($this->met($in, $course, $user), 'Starts in 100 seconds by the clock');
        $clock->bump(100);
        $this->assertTrue($this->met($in, $course, $user), 'Started exactly now');
        $this->assertTrue($this->met(['feature' => 'enrol_start', 'op' => 'inlast', 'val' => 1], $course, $user));
        $clock->bump(200);
        $this->assertFalse($this->met($in, $course, $user), 'Ended exactly now');
        $this->assertGreaterThan(0, $DB->count_records('user_enrolments', ['userid' => $user->id]));
    }

    /**
     * Absolute times ("before", "after") compare with the stored value; the clock decides nothing there.
     */
    public function test_absolute_times_do_not_depend_on_the_clock(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::FROZEN);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'lastlogin', self::FROZEN - 50, ['id' => $user->id]);
        $DB->set_field('user', 'currentlogin', self::FROZEN - 50, ['id' => $user->id]);
        $after = ['feature' => 'site_lastlogin', 'op' => 'after', 'val' => self::FROZEN - 100];
        reader::reset();
        $this->assertTrue($this->met($after, $course, $user));
        $clock->bump(10 * YEARSECS);
        reader::reset();
        $this->assertTrue($this->met($after, $course, $user));
    }
}
