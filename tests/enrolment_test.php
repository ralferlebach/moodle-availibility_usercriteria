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
 * Tests for the enrolment characteristics: active enrolments, time limits, several enrolments, missing enrolment.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\reader\enrolment
 */
final class enrolment_test extends auc_testcase {
    /**
     * Enrol a user with an enrolment method, optionally with a time window and status.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $user User.
     * @param string $method Enrolment method.
     * @param array $set Fields of the enrolment: timestart, timeend, status.
     * @param int $instancestatus Status of the enrolment instance.
     * @return int The enrolment record id.
     */
    private function enrol(
        \stdClass $course,
        \stdClass $user,
        string $method,
        array $set = [],
        int $instancestatus = ENROL_INSTANCE_ENABLED
    ): int {
        global $DB;
        $plugin = enrol_get_plugin($method);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $method]);
        if (!$instance) {
            $plugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $method], '*', MUST_EXIST);
        }
        $DB->set_field('enrol', 'status', $instancestatus, ['id' => $instance->id]);
        $plugin->enrol_user($instance, $user->id, null, $set['timestart'] ?? 0, $set['timeend'] ?? 0, $set['status'] ?? null);
        return (int) $DB->get_field('user_enrolments', 'id', ['userid' => $user->id, 'enrolid' => $instance->id], MUST_EXIST);
    }

    /**
     * Cases: [description, enrolments, now offset, expected methods or null for none].
     *
     * Each enrolment is [method, timestart offset or null, timeend offset or null, user status, instance status].
     * Offsets are seconds relative to the frozen clock.
     *
     * @return array
     */
    public static function matrix(): array {
        $on = ENROL_USER_ACTIVE;
        $off = ENROL_USER_SUSPENDED;
        $inst = ENROL_INSTANCE_ENABLED;
        $instoff = ENROL_INSTANCE_DISABLED;
        return [
            'no enrolment' => [[], null],
            'manual, no limits' => [[['manual', null, null, $on, $inst]], ['manual']],
            'manual, started in the past' => [[['manual', -100, null, $on, $inst]], ['manual']],
            'manual, start in the future' => [[['manual', 100, null, $on, $inst]], null],
            'manual, ended in the past' => [[['manual', -200, -100, $on, $inst]], null],
            'manual, ends in the future' => [[['manual', -200, 100, $on, $inst]], ['manual']],
            'manual, ends exactly now' => [[['manual', -200, 0, $on, $inst]], null],
            'manual suspended' => [[['manual', null, null, $off, $inst]], null],
            'manual with disabled instance' => [[['manual', null, null, $on, $instoff]], null],
            'two methods, both active' => [
                [['manual', null, null, $on, $inst], ['self', null, null, $on, $inst]],
                ['manual', 'self'],
            ],
            'two methods, one suspended' => [[['manual', null, null, $off, $inst], ['self', null, null, $on, $inst]], ['self']],
            'two methods, one expired, one future' => [[['manual', -200, -100, $on, $inst], ['self', 100, null, $on, $inst]], null],
        ];
    }

    /**
     * Which methods count as active for each combination of enrolments.
     *
     * @dataProvider matrix
     * @param array $enrolments Enrolments of the person.
     * @param array|null $expected Expected active methods, or null for no active enrolment.
     */
    public function test_active_methods(array $enrolments, ?array $expected): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        foreach ($enrolments as [$method, $start, $end, $status, $instancestatus]) {
            $this->enrol($course, $user, $method, [
                'timestart' => $start === null ? 0 : $clock->time() + $start,
                'timeend' => $end === null ? 0 : $clock->time() + $end,
                'status' => $status,
            ], $instancestatus);
        }
        reader::reset();
        $feature = \availability_usercriteria\local\catalog::get('enrol_method');
        $value = reader::read($feature, $user->id, $course, $clock->time());
        if ($expected === null) {
            $this->assertTrue($value->is_missing(), 'No active enrolment has no value at all');
        } else {
            $actual = $value->get();
            sort($actual);
            sort($expected);
            $this->assertSame($expected, $actual);
        }
    }

    /**
     * "Is one of / is none of" over the methods, including the person who is not enrolled at all.
     */
    public function test_method_matching_and_the_empty_set(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $course = $this->getDataGenerator()->create_course();
        $enrolled = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $both = $this->getDataGenerator()->create_user();
        $this->enrol($course, $enrolled, 'manual');
        $this->enrol($course, $both, 'manual');
        $this->enrol($course, $both, 'self');

        $in = ['feature' => 'enrol_method', 'op' => 'in', 'val' => ['self']];
        $notin = ['feature' => 'enrol_method', 'op' => 'notin', 'val' => ['self']];
        $absent = ['feature' => 'enrol_method', 'op' => 'missing'];

        $this->assertFalse($this->met($in, $course, $enrolled));
        $this->assertTrue($this->met($notin, $course, $enrolled), 'Enrolled, but not via self');
        $this->assertTrue($this->met($in, $course, $both), 'One matching active enrolment is enough');
        $this->assertFalse($this->met($notin, $course, $both), 'Not via self is false when one enrolment is via self');

        // Not enrolled at all: "not via self" must NOT be true. That is the decision of 0.2.0.
        $this->assertFalse($this->met($in, $course, $stranger));
        $this->assertFalse($this->met($notin, $course, $stranger), 'A person who is not enrolled is not "not enrolled via self"');
        $this->assertFalse($this->met($notin, $course, $stranger, true), 'Also not under NOT');
        $this->assertTrue($this->met($absent, $course, $stranger), 'The explicit way to ask for no active enrolment');
        $this->assertFalse($this->met($absent, $course, $enrolled));
    }

    /**
     * Enrolment start: validity start, creation time with no start, future start, several enrolments.
     */
    public function test_start_semantics(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // A validity start wins over the creation time.
        $id = $this->enrol($course, $user, 'manual', ['timestart' => $clock->time() - 1000]);
        $DB->set_field('user_enrolments', 'timecreated', $clock->time() - 5000, ['id' => $id]);
        reader::reset();
        $start = ['feature' => 'enrol_start', 'op' => 'between', 'val' => $clock->time() - 1000, 'val2' => $clock->time() - 1000];
        $this->assertTrue($this->met($start, $course, $user));

        // With no validity start (0) the creation time is the start.
        $DB->set_field('user_enrolments', 'timestart', 0, ['id' => $id]);
        reader::reset();
        $created = ['feature' => 'enrol_start', 'op' => 'between', 'val' => $clock->time() - 5000, 'val2' => $clock->time() - 5000];
        $this->assertTrue($this->met($created, $course, $user));

        // A creation time in the future with no validity start (inconsistent data) is "not started": no value.
        $DB->set_field('user_enrolments', 'timecreated', $clock->time() + 5000, ['id' => $id]);
        reader::reset();
        $this->assertTrue($this->met(['feature' => 'enrol_start', 'op' => 'missing'], $course, $user));
        $this->assertFalse($this->met(['feature' => 'enrol_start', 'op' => 'after', 'val' => 0], $course, $user));

        // Several enrolments: one matching start is enough.
        $other = $this->enrol($course, $user, 'self', ['timestart' => $clock->time() - 100]);
        reader::reset();
        $this->assertTrue($this->met(['feature' => 'enrol_start', 'op' => 'after', 'val' => $clock->time() - 200], $course, $user));
        $this->assertTrue($this->met(['feature' => 'enrol_start', 'op' => 'before', 'val' => $clock->time() - 50], $course, $user));
        $this->assertGreaterThan(0, $other);
    }

    /**
     * A time limit that passes inside one request is honoured, with the raw rows read only once.
     */
    public function test_time_limits_passing_within_one_request(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->enrol($course, $user, 'manual', ['timestart' => $clock->time() + 100, 'timeend' => $clock->time() + 300]);
        $in = ['feature' => 'enrol_method', 'op' => 'in', 'val' => ['manual']];
        $present = ['feature' => 'enrol_start', 'op' => 'present'];
        reader::reset();

        $reads = $DB->perf_get_reads();
        $this->assertFalse($this->met($in, $course, $user), 'Before the start');
        $clock->bump(100);
        $this->assertTrue($this->met($in, $course, $user), 'The start has passed in the same request');
        $this->assertTrue($this->met($present, $course, $user));
        $clock->bump(199);
        $this->assertTrue($this->met($in, $course, $user), 'One second before the end');
        $clock->bump(1);
        $this->assertFalse($this->met($in, $course, $user), 'The end has passed in the same request');
        $this->assertFalse($this->met($present, $course, $user));
        $clock->set_to(1_800_000_000);
        $this->assertFalse($this->met($in, $course, $user), 'And back before the start');

        $this->assertLessThanOrEqual(2, $DB->perf_get_reads() - $reads, 'The enrolment rows were read once, not per evaluation');
    }

    /**
     * Within one unchanged moment the same data is read only once.
     */
    public function test_one_read_per_feature_user_and_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->enrol($course, $user, 'manual');
        reader::reset();
        $reads = $DB->perf_get_reads();
        for ($i = 0; $i < 40; $i++) {
            $this->met(['feature' => 'enrol_method', 'op' => 'in', 'val' => ['manual']], $course, $user);
            $this->met(['feature' => 'enrol_start', 'op' => 'present'], $course, $user);
        }
        $this->assertLessThanOrEqual(2, $DB->perf_get_reads() - $reads);
    }
}
