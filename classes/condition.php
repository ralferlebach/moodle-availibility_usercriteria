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

use availability_usercriteria\local\catalog;
use availability_usercriteria\local\comparator;
use availability_usercriteria\local\reader;

/**
 * One atomic user criterion: characteristic, operator and operand.
 *
 * Combination with other conditions (AND, OR, NOT) is left to Moodle's restrict access groups. The
 * stored structure is versioned and holds no personal data of the people it is evaluated for.
 *
 * Fail-closed rule: if the value of the person cannot be determined, or the stored rule no longer
 * fits the site (for example a deleted profile field), the condition is not met - also when it is
 * negated. The operators "not available" and "available" are the deliberate way to ask about missing
 * data.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int Version of the stored structure. */
    public const SCHEMA_VERSION = 1;

    /** @var string Characteristic key. */
    protected $feature;

    /** @var string Operator key. */
    protected $operator;

    /** @var mixed First operand, or null. */
    protected $operand;

    /** @var mixed Second operand, or null. */
    protected $operand2;

    /** @var string Limits of "between" that belong to the interval: both, lower, upper or none. */
    protected $bounds = 'both';

    /**
     * Constructor from the stored structure.
     *
     * @param \stdClass $structure Decoded JSON structure.
     * @throws \coding_exception If the structure is malformed.
     */
    public function __construct($structure) {
        $version = $structure->v ?? self::SCHEMA_VERSION;
        if (!is_int($version) || $version !== self::SCHEMA_VERSION) {
            throw new \coding_exception('Unsupported schema version for usercriteria condition');
        }
        if (
            !isset($structure->feature) || !is_string($structure->feature)
                || !preg_match('/^[a-z][a-z0-9_]*(:[A-Za-z0-9_\-]+)?$/', $structure->feature)
        ) {
            throw new \coding_exception('Missing or invalid ->feature for usercriteria condition');
        }
        if (!isset($structure->op) || !is_string($structure->op) || !in_array($structure->op, catalog::all_operators(), true)) {
            throw new \coding_exception('Missing or invalid ->op for usercriteria condition');
        }
        $this->feature = $structure->feature;
        $this->operator = $structure->op;
        $this->operand = null;
        $this->operand2 = null;

        switch (catalog::operand_kind($this->operator)) {
            case 'none':
                break;
            case 'single':
                if (in_array($this->operator, ['gt', 'gte', 'lt', 'lte', 'before', 'after'], true)) {
                    $this->operand = self::require_number($structure, 'val');
                } else {
                    $this->operand = self::require_scalar($structure, 'val');
                }
                break;
            case 'pair':
                $this->operand = self::require_number($structure, 'val');
                $this->operand2 = self::require_number($structure, 'val2');
                if ($this->operand > $this->operand2) {
                    throw new \coding_exception('Lower bound above upper bound for usercriteria condition');
                }
                // Rules saved by version 0.1.0 have no "inc" and keep both limits included.
                $this->bounds = $structure->inc ?? 'both';
                if (!in_array($this->bounds, ['both', 'lower', 'upper', 'none'], true)) {
                    throw new \coding_exception('Invalid ->inc for usercriteria condition');
                }
                break;
            case 'days':
                $days = self::require_number($structure, 'val');
                if (!is_int($days) || $days < 1) {
                    throw new \coding_exception('Invalid number of days for usercriteria condition');
                }
                $this->operand = $days;
                break;
            case 'list':
                if (!isset($structure->val) || !is_array($structure->val) || !$structure->val) {
                    throw new \coding_exception('Missing or invalid ->val for usercriteria condition');
                }
                foreach ($structure->val as $item) {
                    if (!is_string($item) && !is_int($item)) {
                        throw new \coding_exception('Invalid list item for usercriteria condition');
                    }
                }
                $this->operand = array_map('strval', $structure->val);
                break;
        }
        // Text operands must not be empty: an empty needle would match everything.
        if (
            in_array($this->operator, ['contains', 'notcontains', 'startswith', 'endswith'], true)
                && trim((string) $this->operand) === ''
        ) {
            throw new \coding_exception('Empty text operand for usercriteria condition');
        }
    }

    /**
     * A scalar operand.
     *
     * @param \stdClass $structure Structure.
     * @param string $name Property name.
     * @return string|int|float
     */
    private static function require_scalar(\stdClass $structure, string $name) {
        if (
            !isset($structure->$name) || !(is_string($structure->$name) || is_int($structure->$name)
                || is_float($structure->$name))
        ) {
            throw new \coding_exception("Missing or invalid ->$name for usercriteria condition");
        }
        return $structure->$name;
    }

    /**
     * A numeric operand.
     *
     * @param \stdClass $structure Structure.
     * @param string $name Property name.
     * @return int|float
     */
    private static function require_number(\stdClass $structure, string $name) {
        if (!isset($structure->$name) || !(is_int($structure->$name) || is_float($structure->$name))) {
            throw new \coding_exception("Missing or invalid ->$name for usercriteria condition");
        }
        return $structure->$name;
    }

    /**
     * Stored structure.
     *
     * @return \stdClass
     */
    public function save() {
        $result = (object) [
            'type' => 'usercriteria',
            'v' => self::SCHEMA_VERSION,
            'feature' => $this->feature,
            'op' => $this->operator,
        ];
        if ($this->operand !== null) {
            $result->val = $this->operand;
        }
        if ($this->operand2 !== null) {
            $result->val2 = $this->operand2;
        }
        if ($this->bounds !== 'both') {
            $result->inc = $this->bounds;
        }
        return $result;
    }

    /**
     * Whether the condition is met for a person.
     *
     * @param bool $not Whether the condition is negated.
     * @param \core_availability\info $info Item the condition restricts.
     * @param bool $grabthelot Unused; every read is a single targeted query.
     * @param int $userid User id.
     * @return bool
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $result = $this->evaluate($info->get_course(), (int) $userid);
        if ($result === null) {
            // Undecidable: never released, not even by negation.
            return false;
        }
        return $not ? !$result : $result;
    }

    /**
     * Evaluate without negation.
     *
     * @param \stdClass $course Course.
     * @param int $userid User id.
     * @return bool|null Null if undecidable.
     */
    public function evaluate(\stdClass $course, int $userid): ?bool {
        $feature = catalog::get($this->feature);
        if ($feature === null || !in_array($this->operator, catalog::operators()[$feature['type']], true)) {
            return null;
        }
        // The only source of time in this plugin: the clock API, so that tests can freeze and move it.
        $now = \core\di::get(\core\clock::class)->time();
        $value = reader::read($feature, $userid, $course, $now);
        return comparator::evaluate(
            $feature['type'],
            $this->operator,
            $this->operand,
            $this->operand2,
            $value,
            $now,
            $this->bounds
        );
    }

    /**
     * Human readable description for the restriction message and the editor summary.
     *
     * Values of the person are never shown. For identity characteristics even the configured operand is
     * left out, so a restriction cannot be used to read out who is named in it.
     *
     * @param bool $full Whether this is the full description.
     * @param bool $not Whether the condition is negated.
     * @param \core_availability\info $info Item the condition restricts.
     * @return string
     */
    public function get_description($full, $not, \core_availability\info $info) {
        if (catalog::is_identity($this->feature)) {
            $text = get_string('desc_identity', 'availability_usercriteria');
        } else {
            $text = $this->describe($info);
        }
        return $not ? get_string('desc_not', 'availability_usercriteria', $text) : $text;
    }

    /**
     * Sentence describing the configured criterion.
     *
     * @param \core_availability\info $info Item the condition restricts.
     * @return string
     */
    private function describe(\core_availability\info $info): string {
        $feature = catalog::get($this->feature);
        $a = new \stdClass();
        if ($feature === null) {
            $a->feature = $this->feature;
        } else if (isset($feature['label'])) {
            $a->feature = $feature['label'];
        } else {
            $a->feature = get_string('feature_' . $this->feature, 'availability_usercriteria');
        }
        $a->operator = get_string('op_' . $this->operator, 'availability_usercriteria');
        $type = $feature['type'] ?? 'text';
        switch (catalog::operand_kind($this->operator)) {
            case 'none':
                return get_string('desc_none', 'availability_usercriteria', $a);
            case 'pair':
                $pair = new \stdClass();
                $pair->first = $this->format_operand($this->operand, $type, $feature, $info);
                $pair->second = $this->format_operand($this->operand2, $type, $feature, $info);
                $pair->bounds = get_string('bounds_' . $this->bounds, 'availability_usercriteria');
                $a->operand = get_string('desc_pair', 'availability_usercriteria', $pair);
                break;
            case 'days':
                $a->operand = get_string('desc_days', 'availability_usercriteria', $this->operand);
                break;
            case 'list':
                $items = array_map(fn($item) => $this->format_operand($item, $type, $feature, $info), $this->operand);
                $a->operand = implode(', ', $items);
                break;
            default:
                $a->operand = $this->format_operand($this->operand, $type, $feature, $info);
        }
        return get_string('desc', 'availability_usercriteria', $a);
    }

    /**
     * Display form of one operand.
     *
     * @param mixed $operand The operand.
     * @param string $type Data type of the characteristic.
     * @param array|null $feature Catalog definition.
     * @param \core_availability\info $info Item the condition restricts.
     * @return string
     */
    private function format_operand($operand, string $type, ?array $feature, \core_availability\info $info): string {
        if ($type === 'time') {
            return userdate((int) $operand, get_string('strftimedatetimeshort', 'langconfig'));
        }
        $key = $feature['key'] ?? '';
        if (strpos($key, 'role_') === 0) {
            $names = role_get_names($info->get_context());
            foreach ($names as $role) {
                if ((string) $role->id === (string) $operand) {
                    return $role->localname;
                }
            }
        } else if ($key === 'enrol_method') {
            $component = 'enrol_' . $operand;
            if (get_string_manager()->string_exists('pluginname', $component)) {
                return get_string('pluginname', $component);
            }
        } else if (isset($feature['options'][(string) $operand])) {
            return $feature['options'][(string) $operand];
        }
        return s((string) $operand);
    }

    /**
     * Debug string.
     *
     * @return string
     */
    protected function get_debug_string() {
        // Debug output ends up in logs and error messages: identity and contact operands are redacted.
        if (catalog::is_identity($this->feature)) {
            return $this->feature . ' ' . $this->operator . ' [redacted]';
        }
        $operand = is_array($this->operand) ? implode(',', $this->operand) : (string) $this->operand;
        return $this->feature . ' ' . $this->operator . ' ' . $operand
                . ($this->operand2 !== null ? ' ' . $this->operand2 . ' (' . $this->bounds . ')' : '');
    }
}
