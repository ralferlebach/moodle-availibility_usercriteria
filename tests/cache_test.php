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
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Freshness of the request cache: repeated reads, and changes through Moodle's APIs within one request.
 *
 * The validity model (see docs/Technisches_Blueprint.md, "Cache"): a value is read once per request, person, course
 * and characteristic. A change made through a Moodle API triggers an event, the observer forgets what was read, and
 * the next evaluation in the same request sees the new value. Enrolment activity is worked out from the clock on
 * every evaluation. Direct database writes without an event are not seen until the next request.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\cache_observer
 * @covers     \availability_usercriteria\local\reader
 */
final class cache_test extends auc_testcase {
    /**
     * A repeated evaluation without a change reads nothing from the database.
     */
    public function test_repeated_reads_use_the_cache(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['city' => 'Wuppertal']);
        $rule = ['feature' => 'user_city', 'op' => 'eq', 'val' => 'Wuppertal'];
        reader::reset();
        $this->assertTrue($this->met($rule, $course, $user));
        $before = $DB->perf_get_reads();
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->met($rule, $course, $user));
        }
        $this->assertSame(0, $DB->perf_get_reads() - $before);

        // A direct database write without an event is not seen in the same request: that is the documented limit.
        $DB->set_field('user', 'city', 'Berlin', ['id' => $user->id]);
        $this->assertTrue($this->met($rule, $course, $user), 'Unchanged within the request');
        reader::reset();
        $this->assertFalse($this->met($rule, $course, $user), 'The next request reads the new value');
    }

    /**
     * Profile change through the user API.
     */
    public function test_profile_change(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['city' => 'Wuppertal']);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'team', 'name' => 'Team']);
        $rule = ['feature' => 'user_city', 'op' => 'eq', 'val' => 'Wuppertal'];
        $team = ['feature' => 'profile:team', 'op' => 'eq', 'val' => 'Blue'];
        $this->assertTrue($this->met($rule, $course, $user));
        $this->assertFalse($this->met($team, $course, $user));
        // The user API: \core\user::update_user() from Moodle 5.3, user_update_user() before.
        if (method_exists(\core\user::class, 'update_user')) {
            \core\user::update_user((object) ['id' => $user->id, 'city' => 'Berlin'], false, true);
        } else {
            user_update_user((object) ['id' => $user->id, 'city' => 'Berlin'], false, true);
        }
        $this->assertFalse($this->met($rule, $course, $user), 'Seen in the same request');

        // Custom fields are saved by profile_save_data(); the profile form then triggers user_updated.
        profile_save_data((object) ['id' => $user->id, 'profile_field_team' => 'Blue']);
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $this->assertTrue($this->met($team, $course, $user));
    }

    /**
     * A new custom profile field becomes selectable and readable in the same request.
     */
    public function test_new_profile_field(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull(\availability_usercriteria\local\catalog::get('profile:late'));
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'late', 'name' => 'Late']);
        \core\event\user_info_field_created::create_from_field(
            (object) $GLOBALS['DB']->get_record('user_info_field', ['shortname' => 'late'])
        )->trigger();
        $this->assertNotNull(\availability_usercriteria\local\catalog::get('profile:late'));
        $this->assertTrue($this->met(['feature' => 'profile:late', 'op' => 'missing'], $course, $user));
    }

    /**
     * Role assignment and removal.
     */
    public function test_role_change(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $rule = ['feature' => 'role_course_direct', 'op' => 'in', 'val' => [(string) $teacher]];
        $context = \context_course::instance($course->id);
        $this->assertFalse($this->met($rule, $course, $user));
        role_assign($teacher, $user->id, $context->id);
        $this->assertTrue($this->met($rule, $course, $user), 'Assigned in the same request');
        role_unassign($teacher, $user->id, $context->id);
        $this->assertFalse($this->met($rule, $course, $user), 'Removed in the same request');
    }

    /**
     * Enrolment created, suspended, and its end passed by the clock.
     */
    public function test_enrolment_change(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $rule = ['feature' => 'enrol_method', 'op' => 'in', 'val' => ['manual']];
        $this->assertFalse($this->met($rule, $course, $user));
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $plugin->enrol_user($instance, $user->id, null, 0, $clock->time() + 60);
        $this->assertTrue($this->met($rule, $course, $user), 'Enrolled in the same request');
        $plugin->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);
        $this->assertFalse($this->met($rule, $course, $user), 'Suspended in the same request');
        $plugin->update_user_enrol($instance, $user->id, ENROL_USER_ACTIVE);
        $this->assertTrue($this->met($rule, $course, $user));
        $clock->bump(60);
        $this->assertFalse($this->met($rule, $course, $user), 'The end passed by the clock, no event needed');
    }

    /**
     * Completion through the completion API.
     */
    public function test_completion_change(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $rule = ['feature' => 'completed_count', 'op' => 'gte', 'val' => 1];
        $this->assertFalse($this->met($rule, $course, $user));
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        (new \completion_info($course))->update_state($cm, COMPLETION_COMPLETE, $user->id);
        $this->assertTrue($this->met($rule, $course, $user), 'Completed in the same request');
    }

    /**
     * Grade through the grade API.
     */
    public function test_grade_change(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $item = $this->grade_user($course, $user, 40);
        $rule = ['feature' => 'course_grade', 'op' => 'gte', 'val' => 50];
        $this->assertFalse($this->met($rule, $course, $user));
        // The manual grade triggers user_graded; the course total is then recalculated as the gradebook does.
        $item->update_final_grade($user->id, 80);
        \grade_item::fetch_course_item($course->id)->force_regrading();
        grade_regrade_final_grades($course->id);
        $this->assertTrue($this->met($rule, $course, $user), 'Graded in the same request');
    }

    /**
     * Every observed event exists in this Moodle version and is wired to the observer.
     */
    public function test_observers_are_registered(): void {
        $observers = [];
        foreach (\core\event\manager::get_all_observers() as $eventname => $list) {
            foreach ($list as $observer) {
                if (
                    ($observer->plugin ?? '') === 'availability_usercriteria' || str_contains(
                        (string) $observer->callable,
                        'availability_usercriteria'
                    )
                ) {
                    $observers[] = $eventname;
                }
            }
        }
        foreach (
            ['\core\event\user_updated', '\core\event\role_assigned', '\core\event\user_enrolment_created',
                '\core\event\course_module_completion_updated', '\core\event\user_graded',
                '\core\event\user_info_field_created'] as $eventname
        ) {
            $this->assertContains($eventname, $observers);
            $this->assertTrue(class_exists($eventname), $eventname);
        }
    }
}
