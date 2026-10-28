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
use availability_usercriteria\local\usage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/auc_testcase.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Usage time (L-09) and time from first access to course completion (L-10), from the standard log.
 *
 * Log entries are written with explicit times relative to a frozen clock, so every expectation is exact.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\usage
 * @covers     \availability_usercriteria\local\reader\usage
 * @covers     \availability_usercriteria\task\update_usage
 */
final class usage_test extends auc_testcase {
    /** @var int Frozen time. */
    private const NOW = 1_800_000_000;

    /**
     * Enable the standard log store and freeze the clock.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        $this->mock_clock_with_frozen(self::NOW);
    }

    /**
     * Write one log entry.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $time Time of the event.
     * @param array $extra Other fields.
     */
    private function log(int $userid, int $courseid, int $time, array $extra = []): void {
        global $DB;
        $context = $courseid > SITEID ? \context_course::instance($courseid) : \context_system::instance();
        $DB->insert_record('logstore_standard_log', (object) ($extra + [
            'eventname' => '\core\event\course_viewed', 'component' => 'core', 'action' => 'viewed', 'target' => 'course',
            'crud' => 'r', 'edulevel' => 2, 'contextid' => $context->id, 'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid, 'userid' => $userid, 'courseid' => $courseid, 'anonymous' => 0,
            'timecreated' => $time, 'origin' => 'web', 'realuserid' => null,
        ]));
    }

    /**
     * Sessions: gaps up to 30 minutes count, a longer pause starts a new session, a single click counts nothing.
     */
    public function test_sessions(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $start = self::NOW - 10 * HOURSECS;
        foreach ([0, 600, 1500, 1500 + 1800, 1500 + 1800 + 1801, 1500 + 1800 + 1801 + 100, 20000] as $offset) {
            $this->log($user->id, $course->id, $start + $offset);
        }
        usage::process();
        $row = usage::row($user->id, $course->id);
        // Session 1: 0, 600, 1500, 3300 (gaps 600, 900, 1800 - exactly the limit counts). Session 2: 5101, 5201.
        // Session 3: the single click at 20000.
        $this->assertSame(3300 + 100, (int) $row->seconds);
        $this->assertSame(3, (int) $row->sessions);
        $this->assertSame($start, (int) $row->firstaccess);
        $this->assertSame($start + 20000, (int) $row->lastevent);
    }

    /**
     * The log is processed incrementally: a session continues across two runs, nothing is counted twice, and a run
     * can be limited.
     */
    public function test_incremental(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        // Setting up the course already wrote log entries on databases that do not hold them back until a commit
        // (MariaDB in PHPUnit): count from here.
        usage::process();
        $start = self::NOW - HOURSECS;
        $this->log($user->id, $course->id, $start);
        $this->log($user->id, $course->id, $start + 60);
        $this->assertSame(2, usage::process());
        $this->assertSame(0, usage::process(), 'Nothing new');
        $this->log($user->id, $course->id, $start + 120);
        $this->log($user->id, $course->id, $start + 180);
        $this->log($user->id, $course->id, $start + 240);
        $this->assertSame(2, usage::process(2), 'Limited run');
        $this->assertSame(180, (int) usage::row($user->id, $course->id)->seconds);
        usage::process();
        $row = usage::row($user->id, $course->id);
        $this->assertSame(240, (int) $row->seconds);
        $this->assertSame(1, (int) $row->sessions);
    }

    /**
     * Only events a person caused in a course count: not on the front page, not while logged in as somebody else,
     * not from the command line, not anonymous; deleted courses and people leave no row.
     */
    public function test_which_events_count(): void {
        set_config('coursebinenable', 0, 'tool_recyclebin');
        set_config('categorybinenable', 0, 'tool_recyclebin');
        $course = $this->getDataGenerator()->create_course();
        $gone = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $deleted = $this->getDataGenerator()->create_user();
        $admin = get_admin();
        $t = self::NOW - HOURSECS;
        $this->log($user->id, $course->id, $t);
        $this->log($user->id, $course->id, $t + 100, ['realuserid' => $admin->id]);
        $this->log($user->id, $course->id, $t + 200, ['origin' => 'cli']);
        $this->log($user->id, $course->id, $t + 300, ['anonymous' => 1]);
        $this->log($user->id, SITEID, $t + 400);
        $this->log($user->id, $gone->id, $t + 500);
        $this->log($deleted->id, $course->id, $t + 600);
        $this->log($user->id, $course->id, $t + 700, ['origin' => 'ws']);
        delete_course($gone, false);
        delete_user($deleted);
        usage::process();
        $row = usage::row($user->id, $course->id);
        $this->assertSame(700, (int) $row->seconds, 'Only the web event at 0 and the web service event at 700');
        $this->assertNull(usage::row($user->id, SITEID));
        $this->assertNull(usage::row($user->id, $gone->id));
        $this->assertNull(usage::row($deleted->id, $course->id));
    }

    /**
     * The characteristic in hours; not decidable while the log store is off or the log was never processed.
     */
    public function test_usage_time(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $idle = $this->getDataGenerator()->create_user();
        $atleast = ['feature' => 'usage_time', 'op' => 'gte', 'val' => 0.5];
        $zero = ['feature' => 'usage_time', 'op' => 'eq', 'val' => 0];

        $this->assertFalse($this->met($zero, $course, $idle), 'Never processed');
        $this->assertFalse($this->met($zero, $course, $idle, true), 'Also not under NOT');

        $this->log($user->id, $course->id, self::NOW - 3600);
        $this->log($user->id, $course->id, self::NOW - 1800);
        (new task\update_usage())->execute();
        $this->expectOutputRegex('/[0-9]+ log entries processed/');
        reader::reset();
        $this->assertTrue($this->met($atleast, $course, $user), '30 minutes');
        $this->assertTrue($this->met(['feature' => 'usage_time', 'op' => 'eq', 'val' => 0.5], $course, $user));
        $this->assertTrue($this->met($zero, $course, $idle), 'No logged event: 0 hours');

        set_config('enabled_stores', '', 'tool_log');
        reader::reset();
        $this->assertFalse($this->met($atleast, $course, $user), 'Log store off');
        $this->assertFalse($this->met($atleast, $course, $user, true));
    }

    /**
     * A real event goes through Moodle's log manager into the store and is counted.
     */
    public function test_real_event(): void {
        global $DB;
        // The log store writes when the transaction is committed, so the test must not run inside one.
        $this->preventResetByRollback();
        set_config('buffersize', 0, 'logstore_standard');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        get_log_manager(true);
        $this->setUser($user);
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        get_log_manager()->dispose();
        $where = ['userid' => $user->id, 'courseid' => $course->id];
        $this->assertSame(1, $DB->count_records('logstore_standard_log', $where));
        // Triggered from the command line (as here), the event has the origin "cli" and does not count.
        $this->assertSame('cli', $DB->get_field('logstore_standard_log', 'origin', $where));
        usage::process();
        $this->assertNull(usage::row($user->id, $course->id));
        // The same event from a browser counts.
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        get_log_manager()->dispose();
        $DB->set_field_select(
            'logstore_standard_log',
            'origin',
            'web',
            'userid = ? AND courseid = ? AND origin = ?',
            [$user->id, $course->id, 'cli']
        );
        usage::process();
        $this->assertSame(1, (int) usage::row($user->id, $course->id)->sessions);
    }

    /**
     * Course completion setup: completion on, one criterion.
     *
     * @return \stdClass Course.
     */
    private function completion_course(): \stdClass {
        global $CFG, $DB;
        $CFG->enablecompletion = true;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $DB->insert_record('course_completion_criteria', (object) ['course' => $course->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_SELF]);
        return $course;
    }

    /**
     * Time from the first logged access to course completion, in days.
     */
    public function test_completion_duration(): void {
        global $DB;
        $course = $this->completion_course();
        $done = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $open = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $early = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $first = self::NOW - 10 * DAYSECS;
        foreach ([$done, $open, $early] as $user) {
            $this->log($user->id, $course->id, $first);
        }
        usage::process();
        $complete = function (\stdClass $user, int $time) use ($DB, $course): void {
            $DB->insert_record('course_completions', (object) ['userid' => $user->id, 'course' => $course->id,
                'timeenrolled' => 0, 'timestarted' => 0, 'timecompleted' => $time, 'reaggregate' => 0]);
        };
        $complete($done, $first + (int) (2.5 * DAYSECS));
        $complete($early, $first - DAYSECS);
        reader::reset();

        $this->assertTrue($this->met(['feature' => 'completion_duration', 'op' => 'eq', 'val' => 2.5], $course, $done));
        $this->assertTrue($this->met(['feature' => 'completion_duration', 'op' => 'lt', 'val' => 3], $course, $done));
        $this->assertTrue($this->met(['feature' => 'completion_duration', 'op' => 'missing'], $course, $open), 'Not completed');
        $this->assertFalse($this->met(['feature' => 'completion_duration', 'op' => 'lt', 'val' => 100], $course, $open, true));
        $this->assertTrue(
            $this->met(['feature' => 'completion_duration', 'op' => 'missing'], $course, $early),
            'Completed before the first logged access: unknown'
        );
    }

    /**
     * Without course completion criteria the duration cannot be decided, and teachers are told why.
     */
    public function test_completion_duration_needs_criteria(): void {
        global $CFG;
        $CFG->enablecompletion = true;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        usage::process();
        $rule = ['feature' => 'completion_duration', 'op' => 'missing'];
        $this->assertFalse($this->met($rule, $course, $user));
        $text = $this->cond($rule)->get_description(true, false, $this->info($course, $user->id));
        $this->assertStringContainsString(get_string('diag_nocoursecompletion', 'availability_usercriteria'), $text);
        $student = $this->cond($rule)->get_description(false, false, $this->info($course, $user->id));
        $this->assertStringNotContainsString(get_string('diag_nocoursecompletion', 'availability_usercriteria'), $student);
    }
}
