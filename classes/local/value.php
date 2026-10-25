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
 * Result of reading one characteristic for one person.
 *
 * Three states are kept apart on purpose: a real value, a missing value (the person has none, for
 * example an ungraded course) and an unavailable source (the data cannot be determined at all). A
 * missing grade is never a grade of zero.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class value {
    /** @var int A value is present. */
    public const PRESENT = 0;
    /** @var int The person has no value. */
    public const MISSING = 1;
    /** @var int The source cannot be evaluated. */
    public const UNAVAILABLE = 2;

    /** @var int State of this value. */
    private int $state;

    /** @var mixed Scalar or list of scalars; null unless present. */
    private $data;

    /**
     * Constructor.
     *
     * @param int $state One of the state constants.
     * @param mixed $data The data.
     */
    private function __construct(int $state, $data = null) {
        $this->state = $state;
        $this->data = $data;
    }

    /**
     * A present value. A list (possibly empty) is a definite answer, not a missing one.
     *
     * @param mixed $data Scalar or list of scalars.
     * @return self
     */
    public static function of($data): self {
        return new self(self::PRESENT, $data);
    }

    /**
     * A missing value.
     *
     * @return self
     */
    public static function missing(): self {
        return new self(self::MISSING);
    }

    /**
     * An unavailable source.
     *
     * @return self
     */
    public static function unavailable(): self {
        return new self(self::UNAVAILABLE);
    }

    /**
     * Whether the person has no value.
     *
     * @return bool
     */
    public function is_missing(): bool {
        return $this->state === self::MISSING;
    }

    /**
     * Whether nothing is there: a missing value or an empty list.
     *
     * Used by the explicit "is available" and "is not available" operators only. An empty list is still a
     * definite answer for "is one of" and "is none of".
     *
     * @return bool
     */
    public function is_absent(): bool {
        return $this->state === self::MISSING || ($this->state === self::PRESENT && $this->data === []);
    }

    /**
     * Whether the source cannot be evaluated.
     *
     * @return bool
     */
    public function is_unavailable(): bool {
        return $this->state === self::UNAVAILABLE;
    }

    /**
     * The data.
     *
     * @return mixed
     */
    public function get() {
        return $this->data;
    }

    /**
     * Plain array form for the request cache.
     *
     * @return array
     */
    public function to_array(): array {
        return [$this->state, $this->data];
    }

    /**
     * Rebuild from the array form.
     *
     * @param array $array Result of to_array().
     * @return self
     */
    public static function from_array(array $array): self {
        return new self((int) $array[0], $array[1]);
    }
}
