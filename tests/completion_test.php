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

/**
 * Tests for the activity counts: which states and which activities count.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\reader\completion
 */
final class completion_test extends auc_testcase {
    /**
     * Create a course with completion tracking.
     *
     * @return \stdClass
     */
    private function course(): \stdClass {
        global $CFG;
        $CFG->enablecompletion = true;
        return $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
    }

    /**
     * Create a page with completion tracking.
     *
     * @param \stdClass $course Course.
     * @param array $extra More settings.
     * @return \stdClass The page.
     */
    private function page(\stdClass $course, array $extra = []): \stdClass {
        return $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL] + $extra);
    }

    /**
     * Set a completion state directly, for states the manual interface does not produce.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $page Page.
     * @param \stdClass $user User.
     * @param int $state Completion state.
     */
    private function state(\stdClass $course, \stdClass $page, \stdClass $user, int $state): void {
        global $DB;
        $clock = \core\di::get(\core\clock::class);
        $row = ['coursemoduleid' => $page->cmid, 'userid' => $user->id];
        // Written directly: the completion API would work out the state of automatic completion itself.
        if ($DB->record_exists('course_modules_completion', $row)) {
            $DB->set_field('course_modules_completion', 'completionstate', $state, $row);
        } else {
            $DB->insert_record('course_modules_completion', $row + ['completionstate' => $state, 'viewed' => 0,
                'overrideby' => null, 'timemodified' => $clock->time()]);
        }
        reader::reset();
    }

    /**
     * Each state counts or does not: complete and pass for both, fail only for "finished", incomplete for none.
     */
    public function test_states(): void {
        $this->resetAfterTest();
        $course = $this->course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $states = [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL, COMPLETION_INCOMPLETE];
        foreach ($states as $state) {
            $this->state($course, $this->page($course), $user, $state);
        }
        $two = ['feature' => 'completed_count', 'op' => 'eq', 'val' => 2];
        $this->assertTrue($this->met($two, $course, $user), 'complete and pass');
        $this->assertTrue($this->met(['feature' => 'finished_count', 'op' => 'eq', 'val' => 3], $course, $user), 'and fail');
    }

    /**
     * Manual and automatic completion both count; so does the other kind of tracking.
     */
    public function test_manual_and_automatic(): void {
        $this->resetAfterTest();
        $course = $this->course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $manual = $this->page($course);
        $auto = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $this->state($course, $manual, $user, COMPLETION_COMPLETE);
        $this->state($course, $auto, $user, COMPLETION_COMPLETE);
        $this->assertTrue($this->met(['feature' => 'completed_count', 'op' => 'eq', 'val' => 2], $course, $user));
    }

    /**
     * Hidden and deleted activities and activities without tracking do not count; restricted ones do.
     */
    public function test_which_activities_count(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $visible = $this->page($course);
        $hidden = $this->page($course, ['visible' => 0]);
        $deleting = $this->page($course);
        $untracked = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_NONE]);
        $restricted = $this->page($course, ['availability' => json_encode(\core_availability\tree::get_root_json(
            [\availability_date\condition::get_json('>=', 4102444800)]
        ))]);
        foreach ([$visible, $hidden, $deleting, $restricted] as $page) {
            $this->state($course, $page, $user, COMPLETION_COMPLETE);
        }
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $deleting->cmid]);
        rebuild_course_cache($course->id, true);
        reader::reset();
        $this->assertTrue(
            $this->met(['feature' => 'completed_count', 'op' => 'eq', 'val' => 2], $course, $user),
            'visible and restricted count; hidden, being deleted and untracked do not (' . $untracked->cmid . ')'
        );
    }

    /**
     * The counted population in detail: hidden sections, stealth activities, activities locked by another
     * condition, and the role of the person.
     */
    public function test_population_details(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->allowstealth = 1;
        $course = $this->course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $plain = $this->page($course);
        $insection = $this->page($course, ['section' => 1]);
        $stealth = $this->page($course, ['visibleoncoursepage' => 0]);
        $locked = $this->page($course, ['availability' => json_encode(\core_availability\tree::get_root_json(
            [\availability_completion\condition::get_json($plain->cmid, COMPLETION_COMPLETE)]
        ))]);
        foreach ([$student, $teacher] as $user) {
            foreach ([$insection, $stealth, $locked] as $page) {
                $this->state($course, $page, $user, COMPLETION_COMPLETE);
            }
        }
        $count = fn(\stdClass $user, int $n) => $this->met(
            ['feature' => 'completed_count', 'op' => 'eq', 'val' => $n],
            $course,
            $user
        );
        $this->assertTrue($count($student, 3), 'Section activity, stealth activity and the locked activity count');
        $this->assertTrue($count($teacher, 3), 'The role of the person does not change what counts');

        \core_courseformat\formatactions::section($course)->update(
            get_fast_modinfo($course)->get_section_info(1),
            ['visible' => 0]
        );
        rebuild_course_cache($course->id, true);
        reader::reset();
        $this->assertTrue($count($student, 2), 'An activity in a hidden section is hidden and does not count');
    }

    /**
     * Two activities depending on each other through this plugin and core's completion condition: no loop, and
     * each verdict follows from the stored states.
     */
    public function test_mutual_dependency(): void {
        $this->resetAfterTest();
        $course = $this->course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $first = $this->page($course);
        $second = $this->page($course, ['availability' => json_encode(\core_availability\tree::get_root_json(
            [\availability_completion\condition::get_json($first->cmid, COMPLETION_COMPLETE)]
        ))]);
        $this->restrict($first->cmid, $course, [['feature' => 'completed_count', 'op' => 'gte', 'val' => 1]]);
        $this->state($course, $second, $user, COMPLETION_COMPLETE);
        get_fast_modinfo($course, 0, true);
        $modinfo = get_fast_modinfo($course, $user->id);
        $this->assertTrue($modinfo->get_cm($first->cmid)->uservisible, 'The second activity is complete: one counts');
        $this->assertFalse($modinfo->get_cm($second->cmid)->uservisible, 'The first activity is not complete');
    }

    /**
     * One person's states never count for another person.
     */
    public function test_other_users(): void {
        $this->resetAfterTest();
        $course = $this->course();
        $one = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $two = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->state($course, $this->page($course), $one, COMPLETION_COMPLETE);
        $this->assertTrue($this->met(['feature' => 'completed_count', 'op' => 'eq', 'val' => 1], $course, $one));
        $this->assertTrue($this->met(['feature' => 'completed_count', 'op' => 'eq', 'val' => 0], $course, $two));
    }

    /**
     * Completion tracking switched off in the course: not decidable, also under NOT.
     */
    public function test_tracking_disabled(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['completed_count', 'finished_count'] as $feature) {
            $this->assertFalse($this->met(['feature' => $feature, 'op' => 'lt', 'val' => 1], $course, $user));
            $this->assertFalse($this->met(['feature' => $feature, 'op' => 'lt', 'val' => 1], $course, $user, true));
        }
    }

    /**
     * A restriction on an activity that counts itself must not loop.
     */
    public function test_no_loop_when_the_restricted_activity_counts_itself(): void {
        $this->resetAfterTest();
        $course = $this->course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $first = $this->page($course);
        $second = $this->page($course);
        $this->state($course, $first, $user, COMPLETION_COMPLETE);
        // Both activities are restricted by "at least one completed activity", and both count themselves.
        foreach ([$first, $second] as $page) {
            $this->restrict($page->cmid, $course, [['feature' => 'completed_count', 'op' => 'gte', 'val' => 1]]);
        }
        reader::reset();
        $modinfo = get_fast_modinfo($course, $user->id);
        $this->assertTrue($modinfo->get_cm($first->cmid)->uservisible);
        $this->assertTrue($modinfo->get_cm($second->cmid)->uservisible);
    }

    /**
     * The number of queries does not grow with the number of activities of the course.
     */
    public function test_large_course_does_not_multiply_queries(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $measure = function (int $activities) use ($course, $user, $DB): int {
            for ($i = 0; $i < $activities; $i++) {
                $this->state($course, $this->page($course), $user, COMPLETION_COMPLETE);
            }
            get_fast_modinfo($course, 0, true);
            get_fast_modinfo($course);
            reader::reset();
            $before = $DB->perf_get_reads();
            $this->assertTrue($this->met(['feature' => 'completed_count', 'op' => 'gte', 'val' => 1], $course, $user));
            return $DB->perf_get_reads() - $before;
        };
        $small = $measure(5);
        $large = $measure(60);
        $this->assertLessThanOrEqual($small + 2, $large, "Queries with 5 activities: $small, with 65: $large");
    }
}
