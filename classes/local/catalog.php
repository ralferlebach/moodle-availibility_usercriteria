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
 * Central definition of the characteristics and operators a condition may use.
 *
 * Everything the editor offers and everything the evaluation accepts comes from here, so the two
 * cannot drift apart.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {
    /** @var string Prefix of the key of a custom profile field. */
    public const PROFILE_PREFIX = 'profile:';

    /** @var string Prefix of the key of a custom text profile field read as a number (an explicit opt-in). */
    public const PROFILE_NUMBER_PREFIX = 'profile_num:';

    /** @var string Scope: the value does not depend on the course. */
    public const SITE = 'site';

    /** @var string Scope: the value is read in the course of the restricted item. */
    public const COURSE = 'course';

    /** @var string[] Built-in standard profile fields that may be compared as text. */
    private const STANDARD_PROFILE = [
        'idnumber', 'institution', 'department', 'city', 'country', 'lang',
        'email', 'phone1', 'phone2', 'address', 'middlename', 'alternatename',
    ];

    /**
     * Operators per data type, in the order the editor lists them.
     *
     * @return array
     */
    public static function operators(): array {
        return [
            'text' => ['eq', 'ne', 'contains', 'notcontains', 'startswith', 'endswith', 'missing', 'present'],
            'number' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between', 'missing', 'present'],
            'time' => ['before', 'after', 'between', 'inlast', 'olderthan', 'missing', 'present'],
            'select' => ['in', 'notin', 'missing', 'present'],
        ];
    }

    /**
     * Every known operator.
     *
     * @return string[]
     */
    public static function all_operators(): array {
        static $all = null;
        if ($all === null) {
            $all = array_values(array_unique(array_merge(relative::OPERATORS, ...array_values(self::operators()))));
        }
        return $all;
    }

    /**
     * Whether a characteristic accepts an operator: the operators of its type, and the relative operators for the
     * characteristics that can be compared with the other participants.
     *
     * @param array $feature Catalog definition.
     * @param string $operator Operator key.
     * @return bool
     */
    public static function allows(array $feature, string $operator): bool {
        if (in_array($operator, self::operators()[$feature['type']] ?? [], true)) {
            return true;
        }
        return !empty($feature['relative']) && in_array($operator, relative::OPERATORS, true);
    }

    /**
     * Whether an operator compares with the other participants.
     *
     * @param string $operator Operator key.
     * @return bool
     */
    public static function is_relative(string $operator): bool {
        return in_array($operator, relative::OPERATORS, true);
    }

    /**
     * The kind of operand an operator takes.
     *
     * @param string $operator Operator key.
     * @return string none, single, pair, list, days, count or percent.
     */
    public static function operand_kind(string $operator): string {
        switch ($operator) {
            case 'missing':
            case 'present':
            case 'max':
            case 'min':
                return 'none';
            case 'best':
            case 'worst':
                return 'count';
            case 'toppct':
            case 'bottompct':
                return 'percent';
            case 'between':
                return 'pair';
            case 'in':
            case 'notin':
                return 'list';
            case 'inlast':
            case 'olderthan':
                return 'days';
            default:
                return 'single';
        }
    }

    /**
     * The built-in characteristics.
     *
     * @return array Key => definition.
     */
    private static function builtin(): array {
        // Pure definitions without strings or data: built once per request, not once per evaluated condition.
        static $built = null;
        if ($built !== null) {
            return $built;
        }
        $defs = [
            'user_id' => ['identity', 'number', self::SITE, 'user'],
            'user_username' => ['identity', 'text', self::SITE, 'user'],
            'user_firstname' => ['identity', 'text', self::SITE, 'user'],
            'user_lastname' => ['identity', 'text', self::SITE, 'user'],
            'role_course_direct' => ['role', 'select', self::COURSE, 'role'],
            'role_course_effective' => ['role', 'select', self::COURSE, 'role'],
            'role_system' => ['role', 'select', self::SITE, 'role'],
            'enrol_method' => ['enrolment', 'select', self::COURSE, 'enrolment'],
            'enrol_start' => ['enrolment', 'time', self::COURSE, 'enrolment'],
            'site_lastlogin' => ['activity', 'time', self::SITE, 'user'],
            'course_lastaccess' => ['activity', 'time', self::COURSE, 'activity'],
            'completed_count' => ['progress', 'number', self::COURSE, 'completion'],
            'finished_count' => ['progress', 'number', self::COURSE, 'completion'],
            'course_grade' => ['progress', 'number', self::COURSE, 'grade'],
            'usage_time' => ['activity', 'number', self::COURSE, 'usage'],
            'completion_duration' => ['progress', 'number', self::COURSE, 'usage'],
        ];
        foreach (self::STANDARD_PROFILE as $name) {
            $defs['user_' . $name] = ['profile', 'text', self::SITE, 'user'];
        }
        $result = [];
        foreach ($defs as $key => [$category, $type, $scope, $source]) {
            $result[$key] = [
                'key' => $key,
                'category' => $category,
                'type' => $type,
                'scope' => $scope,
                'source' => $source,
                'relative' => in_array($key, relative::METRICS, true),
            ];
        }
        $built = $result;
        return $result;
    }

    /**
     * Custom profile fields as characteristics.
     *
     * @return array Key => definition, including the field name and, for menus, the options.
     */
    private static function custom_profile_fields(): array {
        global $DB;
        $cache = \cache::make('availability_usercriteria', 'fields');
        $stored = $cache->get('custom');
        if ($stored !== false) {
            return $stored;
        }
        $result = [];
        $fields = $DB->get_records('user_info_field', null, 'sortorder', 'id, shortname, name, datatype, param1');
        foreach ($fields as $field) {
            $key = self::PROFILE_PREFIX . $field->shortname;
            if (!preg_match('/^[A-Za-z0-9_\-]+$/', $field->shortname)) {
                continue;
            }
            $def = [
                'key' => $key,
                'category' => 'profile',
                'type' => 'text',
                'scope' => self::SITE,
                'source' => 'profile',
                'label' => format_string($field->name),
                'shortname' => $field->shortname,
            ];
            if ($field->datatype === 'datetime') {
                $def['type'] = 'time';
            } else if ($field->datatype === 'checkbox') {
                $def['type'] = 'select';
                $def['options'] = ['1' => get_string('yes'), '0' => get_string('no')];
            } else if ($field->datatype === 'menu') {
                $def['type'] = 'select';
                $options = [];
                foreach (preg_split('/\R/', (string) $field->param1) as $option) {
                    $option = trim($option);
                    if ($option !== '') {
                        $options[$option] = format_string($option);
                    }
                }
                $def['options'] = $options;
            }
            $result[$key] = $def;
            if ($field->datatype === 'text') {
                // Free text is never read as a number implicitly. A second characteristic, chosen on purpose,
                // offers the numeric operators for fields that are meant to hold a number.
                $numeric = $def;
                $numeric['key'] = self::PROFILE_NUMBER_PREFIX . $field->shortname;
                $numeric['type'] = 'number';
                $numeric['numeric'] = true;
                $numeric['label'] = get_string('asnumber', 'availability_usercriteria', $def['label']);
                $result[$numeric['key']] = $numeric;
            }
        }
        $cache->set('custom', $result);
        return $result;
    }

    /**
     * Forget the custom profile fields read so far in this request.
     */
    public static function reset(): void {
        \cache::make('availability_usercriteria', 'fields')->purge();
    }

    /**
     * All characteristics, built-in and custom profile fields.
     *
     * @return array Key => definition.
     */
    public static function all(): array {
        return self::builtin() + self::custom_profile_fields();
    }

    /**
     * Look up one characteristic.
     *
     * @param string $key Characteristic key.
     * @return array|null The definition, or null if it is unknown (for example a deleted profile field).
     */
    public static function get(string $key): ?array {
        $builtin = self::builtin();
        if (isset($builtin[$key])) {
            return $builtin[$key];
        }
        if (strpos($key, self::PROFILE_PREFIX) === 0 || strpos($key, self::PROFILE_NUMBER_PREFIX) === 0) {
            $custom = self::custom_profile_fields();
            return $custom[$key] ?? null;
        }
        return null;
    }

    /**
     * Whether a characteristic identifies or contacts a person and so must not be spelled out in descriptions.
     *
     * @param string $key Characteristic key.
     * @return bool
     */
    public static function is_identity(string $key): bool {
        return in_array($key, [
            'user_id', 'user_username', 'user_firstname', 'user_lastname',
            'user_email', 'user_phone1', 'user_phone2', 'user_address', 'user_middlename', 'user_alternatename',
        ], true);
    }
}
