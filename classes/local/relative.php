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

namespace availability_usercriteria\local;

/**
 * Relative criteria (L-11): the position of a person among the participants of the course.
 *
 * Operators: among the best / worst N, the highest / lowest value, among the best / worst P percent. Ties share
 * the better position (competition ranking): with the values 90, 90, 80 both 90s are "the highest" and "among
 * the best 1".
 *
 * The values of the other participants are never read while a page is rendered. A scheduled task (hourly, and
 * on demand for a course that has no current values yet) stores, per course and characteristic, the sorted
 * values of all participants that have one - without any reference to the persons. At evaluation the person's
 * own value is read as it is now and placed into that list:
 *
 * - better = number of stored values above the own value, worse = number below.
 * - among the best N: better + 1 <= N; highest: better = 0; among the best P %: better + 1 <= ceil(P / 100 * n).
 * - the same for the worst with the values below.
 *
 * The stored list still contains the person's own value from the last computation. If it has changed since, the
 * comparison can only come out one position worse, never better, until the next computation: relative criteria
 * may release later than possible, but never wrongly.
 *
 * Not decidable (not met, also under NOT): the person is not a participant, has no value of their own, the list
 * is older than MAX_AGE or not computed yet, nobody has a value.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class relative {
    /** @var string[] Characteristics that can be compared with the other participants. */
    public const METRICS = ['course_grade', 'completed_count', 'usage_time'];

    /** @var string[] Relative operators. */
    public const OPERATORS = ['best', 'worst', 'max', 'min', 'toppct', 'bottompct'];

    /** @var int Oldest list that is still used, in seconds. */
    public const MAX_AGE = 3 * HOURSECS;

    /** @var string Table of the lists. */
    public const TABLE = 'availability_usercriteria_dist';

    /** @var float Values closer than this count as equal. */
    private const EPSILON = 1e-9;

    /**
     * Evaluate a relative operator.
     *
     * @param string $metric Characteristic key.
     * @param string $operator Relative operator.
     * @param mixed $operand N or P, null for highest and lowest.
     * @param value $own The person's own value.
     * @param int $userid User id.
     * @param \stdClass $course Course.
     * @param int $now Current time.
     * @return bool|null Null if not decidable.
     */
    public static function evaluate(
        string $metric,
        string $operator,
        $operand,
        value $own,
        int $userid,
        \stdClass $course,
        int $now
    ): ?bool {
        if ($own->is_unavailable() || $own->is_missing() || !is_numeric($own->get())) {
            return null;
        }
        if (!population::is_participant($userid, $course, $now)) {
            return null;
        }
        $values = self::values((int) $course->id, $metric, $now);
        if ($values === null || !$values) {
            return null;
        }
        $n = count($values);
        $mine = (float) $own->get();
        $worse = self::count_below($values, $mine - self::EPSILON);
        $better = $n - self::count_below($values, $mine + self::EPSILON);
        switch ($operator) {
            case 'max':
                return $better === 0;
            case 'min':
                return $worse === 0;
            case 'best':
                return $better + 1 <= (int) $operand;
            case 'worst':
                return $worse + 1 <= (int) $operand;
            case 'toppct':
                return $better + 1 <= (int) ceil((float) $operand / 100 * $n);
            case 'bottompct':
                return $worse + 1 <= (int) ceil((float) $operand / 100 * $n);
        }
        return null;
    }

    /**
     * Number of values strictly below a limit (binary search in the ascending list).
     *
     * @param float[] $values Ascending values.
     * @param float $limit Limit.
     * @return int
     */
    private static function count_below(array $values, float $limit): int {
        $low = 0;
        $high = count($values);
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if ($values[$middle] < $limit) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }
        return $low;
    }

    /**
     * The current list of a course and characteristic; null if there is none or it is too old.
     *
     * A missing or outdated list asks for a computation in the background.
     *
     * @param int $courseid Course id.
     * @param string $metric Characteristic key.
     * @param int $now Current time.
     * @return float[]|null Ascending values.
     */
    public static function values(int $courseid, string $metric, int $now): ?array {
        global $DB;
        $cache = \cache::make('availability_usercriteria', 'values');
        $key = 'dist_' . $courseid . '_' . $metric;
        $row = $cache->get($key);
        if ($row === false) {
            $row = $DB->get_record(
                self::TABLE,
                ['courseid' => $courseid, 'metric' => $metric],
                'id, vals, population, timecomputed'
            ) ?: null;
            $cache->set($key, $row);
        }
        if (!$row || $row->vals === null || $now - (int) $row->timecomputed > self::MAX_AGE) {
            self::request($courseid);
            return null;
        }
        return array_map('floatval', json_decode($row->vals, true) ?: []);
    }

    /**
     * Ask for the lists of a course to be computed in the background (once, however often it is asked).
     *
     * @param int $courseid Course id.
     */
    public static function request(int $courseid): void {
        static $requested = [];
        if (isset($requested[$courseid]) && !PHPUNIT_TEST) {
            return;
        }
        $requested[$courseid] = true;
        $task = new \availability_usercriteria\task\compute_distribution();
        $task->set_custom_data(['courseid' => $courseid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Compute and store the lists of all characteristics of one course.
     *
     * @param int $courseid Course id.
     * @param int $now Current time.
     */
    public static function compute(int $courseid, int $now): void {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
            return;
        }
        $userids = population::participants($courseid, $now);
        foreach (self::METRICS as $metric) {
            $values = self::collect($metric, $userids, $course);
            $record = ['courseid' => $courseid, 'metric' => $metric,
                'vals' => $values === null ? null : json_encode($values),
                'population' => $values === null ? 0 : count($values), 'timecomputed' => $now];
            $existing = $DB->get_field(self::TABLE, 'id', ['courseid' => $courseid, 'metric' => $metric]);
            if ($existing) {
                $DB->update_record(self::TABLE, (object) (['id' => $existing] + $record));
            } else {
                $DB->insert_record(self::TABLE, (object) $record);
            }
            \cache::make('availability_usercriteria', 'values')->delete('dist_' . $courseid . '_' . $metric);
        }
    }

    /**
     * The values of the participants that have one, ascending.
     *
     * @param string $metric Characteristic key.
     * @param int[] $userids Participants.
     * @param \stdClass $course Course.
     * @return float[]|null Null if the characteristic cannot be read in this course.
     */
    private static function collect(string $metric, array $userids, \stdClass $course): ?array {
        if ($metric === 'usage_time') {
            if (!usage::is_tracking()) {
                return null;
            }
            $values = array_values(usage::hours_many($userids, (int) $course->id));
        } else {
            $read = $metric === 'course_grade' ? reader\grade::read_many($userids, $course)
                : reader\completion::read_many($metric, $userids, $course);
            $values = [];
            foreach ($read as $value) {
                if ($value->is_unavailable() && $userids) {
                    return null;
                }
                if (!$value->is_unavailable() && !$value->is_missing()) {
                    $values[] = (float) $value->get();
                }
            }
        }
        sort($values, SORT_NUMERIC);
        return array_map('floatval', $values);
    }

    /**
     * Ids of the courses whose restrictions use a relative operator.
     *
     * @return int[]
     */
    public static function courses_in_use(): array {
        global $DB;
        $like = $DB->sql_like('availability', ':pattern');
        $courseids = [];
        foreach (['course_modules', 'course_sections'] as $table) {
            $rows = $DB->get_recordset_select($table, $like, ['pattern' => '%"usercriteria"%'], '', 'id, course, availability');
            foreach ($rows as $row) {
                if (!isset($courseids[(int) $row->course]) && self::uses_relative(json_decode((string) $row->availability))) {
                    $courseids[(int) $row->course] = (int) $row->course;
                }
            }
            $rows->close();
        }
        return array_values($courseids);
    }

    /**
     * Whether a restriction tree contains a relative user criteria condition.
     *
     * @param mixed $node Decoded tree or condition.
     * @return bool
     */
    public static function uses_relative($node): bool {
        if (!is_object($node)) {
            return false;
        }
        if (($node->type ?? '') === 'usercriteria' && in_array($node->op ?? '', self::OPERATORS, true)) {
            return true;
        }
        foreach ($node->c ?? [] as $child) {
            if (self::uses_relative($child)) {
                return true;
            }
        }
        return false;
    }
}
