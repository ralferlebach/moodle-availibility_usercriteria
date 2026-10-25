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

/**
 * Editor integration: tells the restrict access form which characteristics and operators to offer.
 *
 * All labels are resolved here, so the JavaScript needs no language strings of its own.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * Language strings the JavaScript needs.
     *
     * @return array
     */
    protected function get_javascript_strings() {
        return [];
    }

    /**
     * Parameters for the JavaScript initialisation.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Activity being edited, if any.
     * @param \section_info|null $section Section being edited, if any.
     * @return array One configuration object.
     */
    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        $component = 'availability_usercriteria';
        $features = [];
        $options = [];
        foreach (catalog::all() as $key => $def) {
            $label = $def['label'] ?? get_string('feature_' . $key, $component);
            $features[] = [
                'key' => $key,
                'label' => $label,
                'category' => $def['category'],
                'type' => $def['type'],
                'scope' => $def['scope'],
            ];
            if (isset($def['options'])) {
                $options[$key] = self::to_list($def['options']);
            }
        }
        // Roles are listed with the names valid where they are read: course names (renamed roles included) for
        // the course, the plain names for the system. Only roles that can exist at that level are offered.
        $options['role_course_direct'] = $options['role_course_effective'] =
            self::roles(\context_course::instance($course->id), CONTEXT_COURSE);
        $options['role_system'] = self::roles(\context_system::instance(), CONTEXT_SYSTEM);
        $methods = [];
        foreach (enrol_get_plugins(true) as $name => $plugin) {
            $methods[] = ['id' => $name, 'name' => get_string('pluginname', 'enrol_' . $name)];
        }
        $options['enrol_method'] = $methods;

        $operators = [];
        foreach (catalog::operators() as $type => $list) {
            foreach ($list as $operator) {
                $operators[$type][] = [
                    'op' => $operator,
                    'label' => get_string('op_' . $operator, $component),
                    'kind' => catalog::operand_kind($operator),
                ];
            }
        }
        $categories = [];
        foreach (['identity', 'profile', 'role', 'enrolment', 'activity', 'progress'] as $category) {
            $categories[$category] = get_string('cat_' . $category, $component);
        }
        $hints = [];
        $hinted = ['course_grade', 'completed_count', 'finished_count', 'course_lastaccess', 'enrol_start', 'site_lastlogin'];
        foreach ($hinted as $key) {
            $hints[$key] = get_string('hint_' . $key, $component);
        }
        $labels = [];
        foreach (['feature', 'operator', 'value', 'value2', 'choose', 'days', 'siteinfo', 'courseinfo', 'bounds'] as $name) {
            $labels[$name] = get_string('editor_' . $name, $component);
        }
        $bounds = [];
        foreach (['both', 'lower', 'upper', 'none'] as $bound) {
            $bounds[] = ['id' => $bound, 'name' => get_string('bounds_' . $bound, $component)];
        }

        return [[
            'features' => $features,
            'categories' => $categories,
            'operators' => $operators,
            'options' => $options,
            'hints' => $hints,
            'labels' => $labels,
            'bounds' => $bounds,
            // The time zone of the person editing, by name: the editor converts a date to a timestamp with the
            // rules that apply on that date, so summer and winter time are handled correctly.
            'tz' => \core_date::get_user_timezone(),
        ]];
    }

    /**
     * Whether the condition may be added here.
     *
     * Editing restrictions is already limited by Moodle to people who may edit the item.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Activity, if any.
     * @param \section_info|null $section Section, if any.
     * @return bool
     */
    protected function allow_add($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        return true;
    }

    /**
     * Roles that exist at a context level, with their names in a context.
     *
     * @param \context $context Context for the role names.
     * @param int $level Context level.
     * @return array List of id and name.
     */
    private static function roles(\context $context, int $level): array {
        $allowed = array_map('intval', array_values(get_roles_for_contextlevels($level)));
        $list = [];
        foreach (role_get_names($context) as $role) {
            if (in_array((int) $role->id, $allowed, true)) {
                $list[] = ['id' => (string) $role->id, 'name' => $role->localname];
            }
        }
        return $list;
    }

    /**
     * Convert an option map to an ordered list for JSON.
     *
     * @param array $map Value => label.
     * @return array
     */
    private static function to_list(array $map): array {
        $list = [];
        foreach ($map as $id => $name) {
            $list[] = ['id' => (string) $id, 'name' => (string) $name];
        }
        return $list;
    }
}
