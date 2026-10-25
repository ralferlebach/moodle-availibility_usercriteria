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
 * Cost of rendering a course page with many restricted activities.
 *
 * Every activity carries the same set of conditions, one per data source. Moodle itself needs about one read per
 * activity to build a course page (also without any restriction), so each measurement is compared with the same
 * course without restrictions. What this plugin adds must not grow with the number of activities: each
 * characteristic is read once per person, course and request.
 * With AUC_PERF_REPORT set to a file name, the measured reads and times are appended there (docs/Testbericht).
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\reader
 * @covers     \availability_usercriteria\condition
 */
final class performance_test extends auc_testcase {
    /**
     * A course with $count activities, each restricted by one condition per source.
     *
     * @param int $count Number of activities.
     * @param bool $restricted Whether the activities carry the conditions.
     * @return array Course and student.
     */
    private function scene(int $count, bool $restricted = true): array {
        global $CFG, $DB;
        $CFG->enablecompletion = true;
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'team' . $count,
            'name' => 'Team']);
        // The generator writes the field without an event; a real site sees it with the next request.
        \availability_usercriteria\local\catalog::reset();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['city' => 'Wuppertal']);
        $this->grade_user($course, $user, 80);
        $student = (string) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $conditions = [
            ['feature' => 'user_city', 'op' => 'eq', 'val' => 'Wuppertal'],
            ['feature' => 'profile:team' . $count, 'op' => 'missing'],
            ['feature' => 'role_course_effective', 'op' => 'in', 'val' => [$student]],
            ['feature' => 'enrol_method', 'op' => 'in', 'val' => ['manual']],
            ['feature' => 'enrol_start', 'op' => 'present'],
            ['feature' => 'site_lastlogin', 'op' => 'missing'],
            ['feature' => 'course_lastaccess', 'op' => 'missing'],
            ['feature' => 'completed_count', 'op' => 'gte', 'val' => 0],
            ['feature' => 'course_grade', 'op' => 'gte', 'val' => 50],
        ];
        $children = [];
        foreach ($conditions as $condition) {
            $children[] = $condition + ['type' => 'usercriteria', 'v' => 1];
        }
        $availability = json_encode(['op' => '&', 'c' => $children, 'showc' => array_fill(0, count($children), true)]);
        for ($i = 0; $i < $count; $i++) {
            $this->getDataGenerator()->create_module('page', ['course' => $course->id,
                'completion' => COMPLETION_TRACKING_MANUAL] + ($restricted ? ['availability' => $availability] : []));
        }
        return [$course, $user];
    }

    /**
     * Render the availability of all activities for the student, as the course page does.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $user Student.
     * @return array Reads, seconds and number of accessible activities.
     */
    private function measure(\stdClass $course, \stdClass $user): array {
        global $DB;
        get_fast_modinfo($course, 0, true);
        get_fast_modinfo($course); // Build the shared course cache outside the measurement.
        get_fast_modinfo($course, 0, true);
        reader::reset();
        $clock = \core\di::get(\core\clock::class);
        $start = $clock->now();
        $before = $DB->perf_get_reads();
        $visible = 0;
        foreach (get_fast_modinfo($course, $user->id)->get_cms() as $cm) {
            $visible += $cm->uservisible ? 1 : 0;
        }
        $reads = $DB->perf_get_reads() - $before;
        $seconds = (float) $clock->now()->format('U.u') - (float) $start->format('U.u');
        return [$reads, $seconds, $visible];
    }

    /**
     * Reads of 10, 100 and 500 restricted activities, against the same courses without restrictions.
     */
    public function test_reads_do_not_grow_with_the_activities(): void {
        $this->resetAfterTest();
        $results = [];
        foreach ([10, 100, 500] as $count) {
            [$course, $user] = $this->scene($count, false);
            [$basereads, $baseseconds] = $this->measure($course, $user);
            [$course, $user] = $this->scene($count);
            [$reads, $seconds, $visible] = $this->measure($course, $user);
            $this->assertSame($count, $visible, "All $count activities are open for the student");
            $results[$count] = ['moodle' => $basereads, 'with_plugin' => $reads, 'added' => $reads - $basereads,
                'seconds_moodle' => round($baseseconds, 3), 'seconds_with_plugin' => round($seconds, 3)];
        }
        // At most one read per characteristic (nine here) plus the custom field list, whatever the number of activities.
        foreach ($results as $count => $result) {
            $this->assertLessThanOrEqual(10, $result['added'], "$count activities: " . json_encode($results));
        }
        $this->assertSame($results[100]['added'], $results[500]['added'], json_encode($results));
        $file = getenv('AUC_PERF_REPORT');
        if ($file) {
            global $CFG, $DB;
            $cells = [];
            foreach ($results as $count => $result) {
                $cells[] = sprintf(
                    '%d: %d / %d (+%d), %.3f s / %.3f s',
                    $count,
                    $result['moodle'],
                    $result['with_plugin'],
                    $result['added'],
                    $result['seconds_moodle'],
                    $result['seconds_with_plugin']
                );
            }
            file_put_contents($file, sprintf(
                "| Moodle %s | %s | %s |\n",
                $CFG->release,
                $DB->get_dbfamily(),
                implode(' | ', $cells)
            ), FILE_APPEND);
        }
    }
}
