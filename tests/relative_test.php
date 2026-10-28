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

use availability_usercriteria\local\population;
use availability_usercriteria\local\reader;
use availability_usercriteria\local\relative;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/auc_testcase.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Relative criteria (L-11): position among the participants, ties, population, freshness of the lists.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\relative
 * @covers     \availability_usercriteria\local\population
 * @covers     \availability_usercriteria\task\update_distributions
 * @covers     \availability_usercriteria\task\compute_distribution
 */
final class relative_test extends auc_testcase {
    /** @var int Frozen time. */
    private const NOW = 1_800_000_000;

    /**
     * A course whose students have the given course grades (percent of 100).
     *
     * @param array $grades Name => grade, null for ungraded.
     * @return array Course and the students by name.
     */
    private function graded(array $grades): array {
        $course = $this->getDataGenerator()->create_course();
        $item = new \grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'M', 'grademax' => 100,
            'grademin' => 0], false);
        $item->insert();
        $people = [];
        foreach ($grades as $name => $grade) {
            $people[$name] = $this->getDataGenerator()->create_and_enrol($course, 'student');
            if ($grade !== null) {
                $item->update_final_grade($people[$name]->id, $grade);
            }
        }
        grade_regrade_final_grades($course->id);
        return [$course, $people];
    }

    /**
     * Compute the lists now, as the task does.
     *
     * @param \stdClass $course Course.
     */
    private function compute(\stdClass $course): void {
        relative::compute((int) $course->id, \core\di::get(\core\clock::class)->time());
        reader::reset();
    }

    /**
     * Highest, lowest, best and worst N, with ties sharing the better position.
     */
    public function test_ranks_and_ties(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        [$course, $p] = $this->graded(['a' => 90, 'b' => 90, 'c' => 80, 'd' => 50, 'e' => 50]);
        $this->compute($course);
        $rule = fn(string $op, $val = null) => ['feature' => 'course_grade', 'op' => $op] + ($val === null ? [] : ['val' => $val]);

        $this->assertTrue($this->met($rule('max'), $course, $p['a']), 'A tie at the top: both are the highest');
        $this->assertTrue($this->met($rule('max'), $course, $p['b']));
        $this->assertFalse($this->met($rule('max'), $course, $p['c']));
        $this->assertTrue($this->met($rule('best', 1), $course, $p['b']));
        $this->assertFalse($this->met($rule('best', 2), $course, $p['c']), '80 is third');
        $this->assertTrue($this->met($rule('best', 3), $course, $p['c']));
        $this->assertTrue($this->met($rule('min'), $course, $p['d']), 'A tie at the bottom');
        $this->assertTrue($this->met($rule('worst', 1), $course, $p['e']));
        $this->assertFalse($this->met($rule('worst', 2), $course, $p['c']), '80 is third from below');
        $this->assertTrue($this->met($rule('worst', 3), $course, $p['c']));
        // NOT works on decided results.
        $this->assertTrue($this->met($rule('max'), $course, $p['c'], true));
    }

    /**
     * Top and bottom P percent: the position must be within the first ceil(P / 100 * n).
     */
    public function test_percentages(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        $grades = [];
        for ($i = 1; $i <= 10; $i++) {
            $grades['s' . $i] = $i * 10;
        }
        [$course, $p] = $this->graded($grades);
        $this->compute($course);
        $top = fn($pct) => ['feature' => 'course_grade', 'op' => 'toppct', 'val' => $pct];
        $bottom = fn($pct) => ['feature' => 'course_grade', 'op' => 'bottompct', 'val' => $pct];
        $this->assertTrue($this->met($top(10), $course, $p['s10']));
        $this->assertFalse($this->met($top(10), $course, $p['s9']));
        $this->assertTrue($this->met($top(20), $course, $p['s9']));
        $this->assertTrue($this->met($top(15), $course, $p['s9']), 'ceil(1.5) = 2 positions');
        $this->assertTrue($this->met($bottom(30), $course, $p['s3']));
        $this->assertFalse($this->met($bottom(30), $course, $p['s4']));
        $this->assertTrue($this->met($top(100), $course, $p['s1']));
    }

    /**
     * Who is compared: participants by the role setting (default student); ungraded people have no value.
     */
    public function test_population(): void {
        global $DB;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        [$course, $p] = $this->graded(['a' => 60, 'b' => 70, 'none' => null]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'manual']);
        $item->update_final_grade($teacher->id, 100);
        grade_regrade_final_grades($course->id);
        $this->compute($course);

        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->assertSame([$student], population::role_ids(), 'Default: the student archetype');
        $this->assertTrue(
            $this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $p['b']),
            'The teacher with 100 is not a participant'
        );
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $teacher), 'Not a participant');
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $teacher, true));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'min'], $course, $p['none']), 'No value');
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'min'], $course, $p['none'], true));
        $this->assertSame(2, (int) $DB->get_field(
            'availability_usercriteria_dist',
            'population',
            ['courseid' => $course->id, 'metric' => 'course_grade']
        ), 'Ungraded people are not in the list');

        // The setting decides: with the teacher role as the participant role, the teacher is compared.
        $editing = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        set_config('participantroles', (string) $editing, 'availability_usercriteria');
        $this->compute($course);
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $teacher));
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $p['b']), 'Now not a participant');

        // A suspended enrolment is no participation.
        set_config('participantroles', (string) $student, 'availability_usercriteria');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        enrol_get_plugin('manual')->update_user_enrol($instance, $p['b']->id, ENROL_USER_SUSPENDED);
        $this->compute($course);
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $p['b']));
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $p['a']));

        // No participant roles at all: nothing can be decided, and teachers see why.
        set_config('participantroles', '', 'availability_usercriteria');
        $this->compute($course);
        $this->assertFalse($this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $p['a']));
        $text = $this->cond(['feature' => 'course_grade', 'op' => 'max'])->get_description(
            true,
            false,
            $this->info($course, $p['a']->id)
        );
        $this->assertStringContainsString(get_string('diag_noparticipantroles', 'availability_usercriteria'), $text);
    }

    /**
     * Freshness: no list yet or an outdated list decides nothing and asks for a computation in the background.
     */
    public function test_freshness(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::NOW);
        [$course, $p] = $this->graded(['a' => 60, 'b' => 70]);
        $max = ['feature' => 'course_grade', 'op' => 'max'];

        $this->assertFalse($this->met($max, $course, $p['b']), 'No list yet');
        $tasks = \core\task\manager::get_adhoc_tasks(task\compute_distribution::class);
        $this->assertCount(1, $tasks, 'A computation is queued once');
        $this->assertFalse($this->met($max, $course, $p['b']));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(task\compute_distribution::class));

        $this->runAdhocTasks(task\compute_distribution::class);
        reader::reset();
        $this->assertTrue($this->met($max, $course, $p['b']));

        $clock->bump(relative::MAX_AGE + 1);
        reader::reset();
        $this->assertFalse($this->met($max, $course, $p['b']), 'Older than the limit');
        $this->assertFalse($this->met($max, $course, $p['b'], true));
        $this->assertNotEmpty($DB->get_records('task_adhoc', ['classname' => '\\' . task\compute_distribution::class]));
    }

    /**
     * The own value is current, the list is from the last computation: a change in between can only cost a position.
     */
    public function test_own_value_changed_since_the_computation(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        [$course, $p] = $this->graded(['up' => 50, 'down' => 95, 'other' => 90]);
        $this->compute($course);
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'manual']);
        $item->update_final_grade($p['up']->id, 99);
        $item->update_final_grade($p['down']->id, 60);
        grade_regrade_final_grades($course->id);
        reader::reset();
        $this->assertTrue(
            $this->met(['feature' => 'course_grade', 'op' => 'max'], $course, $p['up']),
            'Improved: correct at once'
        );
        $this->assertFalse(
            $this->met(['feature' => 'course_grade', 'op' => 'best', 'val' => 2], $course, $p['down']),
            'Worse: the old 95 still counts above, one position too low until the next computation'
        );
        $this->compute($course);
        $this->assertTrue($this->met(['feature' => 'course_grade', 'op' => 'best', 'val' => 3], $course, $p['down']));
    }

    /**
     * Completed activities and usage time can be compared as well.
     */
    public function test_other_characteristics(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        $CFG->enablecompletion = true;
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $busy = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $idle = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $DB->insert_record('course_modules_completion', (object) ['coursemoduleid' => $page->cmid, 'userid' => $busy->id,
            'completionstate' => COMPLETION_COMPLETE, 'viewed' => 0, 'overrideby' => null, 'timemodified' => self::NOW]);
        $DB->insert_record('availability_usercriteria_use', (object) ['userid' => $busy->id, 'courseid' => $course->id,
            'seconds' => 7200, 'sessions' => 2, 'firstaccess' => 1, 'lastevent' => 2, 'timemodified' => 2]);
        set_config('usage_lastlogid', 0, 'availability_usercriteria');
        $this->compute($course);
        foreach (['completed_count', 'usage_time'] as $feature) {
            $this->assertTrue($this->met(['feature' => $feature, 'op' => 'max'], $course, $busy), $feature);
            $this->assertTrue($this->met(['feature' => $feature, 'op' => 'min'], $course, $idle), $feature);
            $this->assertFalse($this->met(['feature' => $feature, 'op' => 'max'], $course, $idle), $feature);
        }
        $this->assertFalse(local\catalog::allows(local\catalog::get('finished_count'), 'max'), 'Only the three are relative');
    }

    /**
     * The hourly task computes the lists of the courses that use relative rules, and only those.
     */
    public function test_task_and_courses_in_use(): void {
        global $DB;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        [$used] = $this->graded(['a' => 1]);
        [$plain] = $this->graded(['a' => 1]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $used->id]);
        $this->restrict($page->cmid, $used, [['feature' => 'course_grade', 'op' => 'gte', 'val' => 1],
            ['feature' => 'course_grade', 'op' => 'best', 'val' => 3]], '|');
        $other = $this->getDataGenerator()->create_module('page', ['course' => $plain->id]);
        $this->restrict($other->cmid, $plain, [['feature' => 'course_grade', 'op' => 'gte', 'val' => 1]]);
        $this->assertSame([(int) $used->id], relative::courses_in_use());
        $this->expectOutputRegex('/lists of 1 courses computed/');
        (new task\update_distributions())->execute();
        $this->assertSame(3, $DB->count_records('availability_usercriteria_dist', ['courseid' => $used->id]));
        $this->assertSame(0, $DB->count_records('availability_usercriteria_dist', ['courseid' => $plain->id]));
    }

    /**
     * Malformed relative rules are refused; the description names the comparison, never values of people.
     */
    public function test_structure_and_description(): void {
        $this->resetAfterTest();
        foreach (
            [['op' => 'best', 'val' => 0], ['op' => 'best', 'val' => 1.5], ['op' => 'toppct', 'val' => 0],
                ['op' => 'toppct', 'val' => 101], ['op' => 'worst']] as $bad
        ) {
            try {
                $this->cond($bad + ['feature' => 'course_grade']);
                $this->fail('Accepted: ' . json_encode($bad));
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('usercriteria', $e->getMessage());
            }
        }
        $course = $this->getDataGenerator()->create_course();
        $info = $this->info($course, 0);
        $text = $this->cond(['feature' => 'course_grade', 'op' => 'best', 'val' => 5])->get_description(false, false, $info);
        $this->assertSame(get_string('feature_course_grade', 'availability_usercriteria') . ' '
            . get_string('op_best', 'availability_usercriteria') . ' '
            . get_string('desc_count', 'availability_usercriteria', 5), $text);
        $saved = $this->cond(['feature' => 'course_grade', 'op' => 'toppct', 'val' => 12.5])->save();
        $this->assertSame(12.5, $saved->val);
    }
}
