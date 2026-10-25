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
 * Tests for the typed comparison.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\local\comparator
 */
final class comparator_test extends \basic_testcase {
    /**
     * Comparison cases: type, operator, operand, operand2, actual value object, expected result.
     *
     * @return array
     */
    public static function cases(): array {
        $now = 1_700_000_000;
        return [
            'number gte equal' => ['number', 'gte', 50, null, value::of(50.0), true],
            'number gte below' => ['number', 'gte', 50, null, value::of(49.99999), false],
            'number gt equal' => ['number', 'gt', 50, null, value::of(50), false],
            'number lt' => ['number', 'lt', 50, null, value::of(10), true],
            'number lte equal' => ['number', 'lte', 50, null, value::of(50), true],
            'number eq' => ['number', 'eq', 7, null, value::of('7'), true],
            'number ne' => ['number', 'ne', 7, null, value::of(8), true],
            'number between lower bound inclusive' => ['number', 'between', 10, 20, value::of(10), true],
            'number between upper bound inclusive' => ['number', 'between', 10, 20, value::of(20), true],
            'number between outside' => ['number', 'between', 10, 20, value::of(20.1), false],
            'number zero is a value' => ['number', 'lt', 1, null, value::of(0), true],
            'number operand not numeric' => ['number', 'eq', 'abc', null, value::of(1), null],
            'text eq case-insensitive' => ['text', 'eq', 'MÜLLER', null, value::of('müller'), true],
            'text ne' => ['text', 'ne', 'a', null, value::of('b'), true],
            'text contains' => ['text', 'contains', 'ller', null, value::of('Müller'), true],
            'text notcontains' => ['text', 'notcontains', 'x', null, value::of('Müller'), true],
            'text startswith' => ['text', 'startswith', 'mü', null, value::of('Müller'), true],
            'text endswith' => ['text', 'endswith', 'ER', null, value::of('Müller'), true],
            'text empty needle matches nothing' => ['text', 'contains', '', null, value::of('abc'), false],
            'text like wildcard is literal' => ['text', 'contains', '%', null, value::of('abc'), false],
            'time before' => ['time', 'before', $now, null, value::of($now - 1), true],
            'time after' => ['time', 'after', $now, null, value::of($now + 1), true],
            'time between inclusive' => ['time', 'between', $now, $now + 10, value::of($now + 10), true],
            'time inlast inside' => ['time', 'inlast', 3, null, value::of($now - 2 * DAYSECS), true],
            'time inlast outside' => ['time', 'inlast', 3, null, value::of($now - 4 * DAYSECS), false],
            'time olderthan' => ['time', 'olderthan', 3, null, value::of($now - 4 * DAYSECS), true],
            'time olderthan recent' => ['time', 'olderthan', 3, null, value::of($now - DAYSECS), false],
            'time any of several enrolments' => ['time', 'after', $now, null, value::of([$now - 5, $now + 5]), true],
            'select in' => ['select', 'in', ['3', '4'], null, value::of(['4']), true],
            'select in numeric' => ['select', 'in', [3], null, value::of([3]), true],
            'select in none' => ['select', 'in', ['3'], null, value::of([]), false],
            'select notin empty list is definite' => ['select', 'notin', ['3'], null, value::of([]), true],
            'select notin overlap' => ['select', 'notin', ['3'], null, value::of(['3', '5']), false],
            'missing with missing operator' => ['number', 'missing', null, null, value::missing(), true],
            'present with value' => ['number', 'present', null, null, value::of(0), true],
            'present with missing' => ['number', 'present', null, null, value::missing(), false],
            'missing value with ordinary operator is undecidable' => ['number', 'lt', 50, null, value::missing(), null],
            'missing value with ne is undecidable' => ['text', 'ne', 'a', null, value::missing(), null],
            'unavailable source is undecidable' => ['number', 'gte', 0, null, value::unavailable(), null],
            'unavailable source with missing operator is undecidable' => ['number', 'missing', null, null,
                value::unavailable(), null],
            'empty list counts as not available' => ['select', 'missing', null, null, value::of([]), true],
            'empty list is not available for present' => ['select', 'present', null, null, value::of([]), false],
            'filled list is available' => ['select', 'present', null, null, value::of(['3']), true],
            'huge id compared exactly' => ['number', 'eq', 9007199254740993, null, value::of(9007199254740992), false],
            'huge id ordered exactly' => ['number', 'gt', 9007199254740992, null, value::of(9007199254740993), true],
            'integer string id compared exactly' => ['number', 'lt', 10, null, value::of('9'), true],
            'decimal within the tolerance equals the integer' => ['number', 'gt', 5, null, value::of(5.0000000001), false],
            'decimal outside the tolerance is greater' => ['number', 'gt', 5, null, value::of(5.000001), true],
            'decimal float error does not flip a threshold' => ['number', 'gte', 0.3, null, value::of(0.1 + 0.2), true],
            'decimal just below the threshold stays below' => ['number', 'gte', 50, null, value::of(49.999996), false],
        ];
    }

    /**
     * Each case yields the expected tri-state result.
     *
     * @dataProvider cases
     * @param string $type Type.
     * @param string $operator Operator.
     * @param mixed $operand Operand.
     * @param mixed $operand2 Second operand.
     * @param value $actual Actual value.
     * @param bool|null $expected Expected result.
     */
    public function test_evaluate(string $type, string $operator, $operand, $operand2, value $actual, ?bool $expected): void {
        $this->assertSame($expected, comparator::evaluate($type, $operator, $operand, $operand2, $actual, 1_700_000_000));
    }

    /**
     * The four combinations of included and excluded limits, for numbers and for time.
     *
     * @return array
     */
    public static function bounds_cases(): array {
        $cases = [];
        // For each type: below, on the lower limit, inside, on the upper limit, above.
        $expected = [
            'both' => [false, true, true, true, false],
            'lower' => [false, true, true, false, false],
            'upper' => [false, false, true, true, false],
            'none' => [false, false, true, false, false],
        ];
        $numbers = [9, 10, 15, 20, 21];
        $times = [1_700_000_099, 1_700_000_100, 1_700_000_150, 1_700_000_200, 1_700_000_201];
        foreach ($expected as $bounds => $results) {
            foreach ($results as $index => $result) {
                $cases["number $bounds #$index"] = ['number', 10, 20, $numbers[$index], $bounds, $result];
                $cases["time $bounds #$index"] = ['time', 1_700_000_100, 1_700_000_200, $times[$index], $bounds, $result];
            }
        }
        return $cases;
    }

    /**
     * Which limits belong to the interval is selectable.
     *
     * @dataProvider bounds_cases
     * @param string $type Type.
     * @param int $lower Lower limit.
     * @param int $upper Upper limit.
     * @param int $actual Value.
     * @param string $bounds Included limits.
     * @param bool $expected Expected result.
     */
    public function test_between_bounds(string $type, int $lower, int $upper, int $actual, string $bounds, bool $expected): void {
        $result = comparator::evaluate($type, 'between', $lower, $upper, value::of($actual), 1_700_000_000, $bounds);
        $this->assertSame($expected, $result);
    }
}
