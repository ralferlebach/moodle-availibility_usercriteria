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

namespace availability_usercriteria\local\reader;

use availability_usercriteria\local\value;

/**
 * Reads a custom profile field through the profile field API.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile {
    /**
     * Read a custom profile field.
     *
     * @param array $feature Catalog definition of the field.
     * @param int $userid User id.
     * @return value
     */
    public static function read(array $feature, int $userid): value {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $shortname = $feature['shortname'] ?? '';
        // Only the value of the one field is used and never leaves this method; the plugin returns a verdict.
        $fields = profile_user_record($userid, false);
        if ($shortname === '' || !property_exists($fields, $shortname)) {
            return value::unavailable();
        }
        $data = $fields->$shortname;
        if (is_array($data) || is_object($data)) {
            return value::unavailable();
        }
        if (!empty($feature['numeric'])) {
            // Opt-in numeric reading of a text field: only plain numbers qualify. Empty is missing; any other
            // text can not be decided and is never turned into a number by guessing.
            $text = trim((string) $data);
            if ($text === '') {
                return value::missing();
            }
            if (!preg_match('/^-?[0-9]+([.,][0-9]+)?$/', $text)) {
                return value::unavailable();
            }
            $number = str_replace(',', '.', $text);
            return value::of(strpos($number, '.') === false ? (int) $number : (float) $number);
        }
        if ($feature['type'] === 'time') {
            return (int) $data > 0 ? value::of((int) $data) : value::missing();
        }
        if (
            $feature['type'] === 'select' && isset($feature['options']) && array_key_exists('1', $feature['options'])
                && array_key_exists('0', $feature['options'])
        ) {
            // A checkbox: no stored answer is missing, "0" is a real answer.
            return $data === null || $data === '' ? value::missing() : value::of((string) (int) $data);
        }
        $text = trim((string) $data);
        return $text === '' ? value::missing() : value::of($text);
    }
}
