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
 * Typed, deterministic comparison of a read value against a configured operand.
 *
 * There is no SQL and no free expression here: text matching is done in PHP on plain strings, so
 * nothing needs escaping and an operand can never be interpreted as anything but data.
 *
 * The result is tri-state. null means "cannot be decided" and the caller must treat it as not
 * fulfilled even under a NOT, so that missing data never releases anything.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparator {
    /** @var float Tolerance for decimals only; integers are compared exactly. */
    private const EPSILON = 1e-9;

    /**
     * Evaluate one comparison.
     *
     * @param string $type Data type: text, number, time or select.
     * @param string $operator Operator key.
     * @param mixed $operand First operand.
     * @param mixed $operand2 Second operand (upper bound of between).
     * @param value $actual The value read for the person.
     * @param int $now Current time (from the clock API), for relative time operators.
     * @param string $bounds Limits of "between": both, lower, upper or none are included.
     * @return bool|null True/false, or null if undecidable.
     */
    public static function evaluate(
        string $type,
        string $operator,
        $operand,
        $operand2,
        value $actual,
        int $now,
        string $bounds = 'both'
    ): ?bool {
        if ($actual->is_unavailable()) {
            return null;
        }
        if ($operator === 'missing') {
            return $actual->is_absent();
        }
        if ($operator === 'present') {
            return !$actual->is_absent();
        }
        if ($actual->is_missing()) {
            return null;
        }
        $data = $actual->get();
        $items = is_array($data) ? array_values($data) : [$data];

        if ($type === 'select') {
            return self::select($operator, $operand, $items);
        }
        if (!$items) {
            return null;
        }

        // Negative operators hold when no item matches the positive counterpart.
        $negative = ['ne' => 'eq', 'notcontains' => 'contains'];
        if (isset($negative[$operator])) {
            $any = self::any($type, $negative[$operator], $operand, $operand2, $items, $now, $bounds);
            return $any === null ? null : !$any;
        }
        return self::any($type, $operator, $operand, $operand2, $items, $now, $bounds);
    }

    /**
     * Whether at least one item satisfies a positive operator.
     *
     * @param string $type Data type.
     * @param string $operator Positive operator.
     * @param mixed $operand First operand.
     * @param mixed $operand2 Second operand.
     * @param array $items Values of the person.
     * @param int $now Current time.
     * @param string $bounds Limits of "between".
     * @return bool|null Null if the operand does not fit the type.
     */
    private static function any(
        string $type,
        string $operator,
        $operand,
        $operand2,
        array $items,
        int $now,
        string $bounds
    ): ?bool {
        foreach ($items as $item) {
            if ($type === 'text') {
                $result = self::text($operator, $operand, (string) $item);
            } else if ($type === 'time') {
                $result = self::time($operator, $operand, $operand2, (int) $item, $now, $bounds);
            } else {
                $result = self::number($operator, $operand, $operand2, $item, $bounds);
            }
            if ($result === null) {
                return null;
            }
            if ($result) {
                return true;
            }
        }
        return false;
    }

    /**
     * Text comparison, case-insensitive and multibyte safe.
     *
     * @param string $operator Operator.
     * @param mixed $operand Operand.
     * @param string $actual Actual text.
     * @return bool|null
     */
    private static function text(string $operator, $operand, string $actual): ?bool {
        if (!is_scalar($operand)) {
            return null;
        }
        $needle = \core_text::strtolower((string) $operand);
        $haystack = \core_text::strtolower($actual);
        switch ($operator) {
            case 'eq':
                return $haystack === $needle;
            case 'contains':
                return $needle !== '' && \core_text::strpos($haystack, $needle) !== false;
            case 'startswith':
                return $needle !== '' && str_starts_with($haystack, $needle);
            case 'endswith':
                return $needle !== '' && str_ends_with($haystack, $needle);
        }
        return null;
    }

    /**
     * Three-way comparison of two numbers.
     *
     * Integers (user ids, counts, timestamps) are compared exactly. Only where a decimal is involved is a small
     * tolerance used, so that the float error of a division cannot flip a threshold.
     *
     * @param mixed $first First number.
     * @param mixed $second Second number.
     * @return int -1, 0 or 1.
     */
    private static function compare($first, $second): int {
        if (self::is_integral($first) && self::is_integral($second)) {
            return (int) $first <=> (int) $second;
        }
        $difference = (float) $first - (float) $second;
        if (abs($difference) < self::EPSILON) {
            return 0;
        }
        return $difference < 0 ? -1 : 1;
    }

    /**
     * Whether a value is a whole number.
     *
     * @param mixed $number Value.
     * @return bool
     */
    private static function is_integral($number): bool {
        return is_int($number) || (is_string($number) && preg_match('/^-?[0-9]+$/', $number) === 1);
    }

    /**
     * Whether a value lies between two limits.
     *
     * @param mixed $actual The value.
     * @param mixed $lower Lower limit.
     * @param mixed $upper Upper limit.
     * @param string $bounds both, lower, upper or none: which limits belong to the interval.
     * @return bool
     */
    private static function within($actual, $lower, $upper, string $bounds): bool {
        $low = self::compare($actual, $lower);
        $high = self::compare($actual, $upper);
        $lowok = in_array($bounds, ['both', 'lower'], true) ? $low >= 0 : $low > 0;
        $highok = in_array($bounds, ['both', 'upper'], true) ? $high <= 0 : $high < 0;
        return $lowok && $highok;
    }

    /**
     * Numeric comparison.
     *
     * @param string $operator Operator.
     * @param mixed $operand Lower bound or the operand.
     * @param mixed $operand2 Upper bound for between.
     * @param mixed $actual Actual number.
     * @param string $bounds Limits of "between".
     * @return bool|null
     */
    private static function number(string $operator, $operand, $operand2, $actual, string $bounds): ?bool {
        if (!is_numeric($actual) || !is_numeric($operand)) {
            return null;
        }
        switch ($operator) {
            case 'eq':
                return self::compare($actual, $operand) === 0;
            case 'gt':
                return self::compare($actual, $operand) > 0;
            case 'gte':
                return self::compare($actual, $operand) >= 0;
            case 'lt':
                return self::compare($actual, $operand) < 0;
            case 'lte':
                return self::compare($actual, $operand) <= 0;
            case 'between':
                return is_numeric($operand2) ? self::within($actual, $operand, $operand2, $bounds) : null;
        }
        return null;
    }

    /**
     * Time comparison on UTC timestamps.
     *
     * @param string $operator Operator.
     * @param mixed $operand Timestamp, or number of days for the relative operators.
     * @param mixed $operand2 Upper bound for between.
     * @param int $actual Actual timestamp.
     * @param int $now Current time.
     * @param string $bounds Limits of "between".
     * @return bool|null
     */
    private static function time(string $operator, $operand, $operand2, int $actual, int $now, string $bounds): ?bool {
        if (!is_numeric($operand)) {
            return null;
        }
        $operand = (int) $operand;
        switch ($operator) {
            case 'before':
                return $actual < $operand;
            case 'after':
                return $actual > $operand;
            case 'between':
                return is_numeric($operand2) ? self::within($actual, $operand, (int) $operand2, $bounds) : null;
            case 'inlast':
                return $actual >= $now - $operand * DAYSECS && $actual <= $now;
            case 'olderthan':
                return $actual < $now - $operand * DAYSECS;
        }
        return null;
    }

    /**
     * Selection comparison against a list of allowed values.
     *
     * @param string $operator in or notin.
     * @param mixed $operand List of allowed values.
     * @param array $items Values of the person (possibly empty).
     * @return bool|null
     */
    private static function select(string $operator, $operand, array $items): ?bool {
        if (!is_array($operand)) {
            return null;
        }
        $wanted = array_map('strval', $operand);
        $have = array_map('strval', $items);
        $overlap = (bool) array_intersect($have, $wanted);
        if ($operator === 'in') {
            return $overlap;
        }
        if ($operator === 'notin') {
            return !$overlap;
        }
        return null;
    }
}
