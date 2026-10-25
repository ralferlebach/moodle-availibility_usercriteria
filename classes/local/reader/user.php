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
 * Reads identity, standard profile fields and the site login from the user record.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user {
    /**
     * Read a characteristic.
     *
     * @param string $key Characteristic key.
     * @param int $userid User id.
     * @return value
     */
    public static function read(string $key, int $userid): value {
        global $DB;
        $record = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$record) {
            return value::unavailable();
        }
        if ($key === 'site_lastlogin') {
            // The latest login is the greater of the previous and the current login; 0 means never.
            $latest = max((int) $record->lastlogin, (int) $record->currentlogin);
            return $latest > 0 ? value::of($latest) : value::missing();
        }
        $field = substr($key, strlen('user_'));
        if (!property_exists($record, $field)) {
            return value::unavailable();
        }
        if ($field === 'id') {
            return value::of((int) $record->id);
        }
        $text = trim((string) $record->$field);
        return $text === '' ? value::missing() : value::of($text);
    }
}
