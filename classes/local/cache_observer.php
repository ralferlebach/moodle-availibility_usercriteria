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
 * Keeps the request cache fresh when Moodle changes the data it holds during the same request.
 *
 * The cache lives for one request only. Within that request a value can still change, for example when an
 * activity is completed, a grade is saved or an enrolment is created and Moodle then renders the course page in
 * the same request. The observed events cover the data this plugin reads; each one simply forgets everything
 * read so far, which is cheap because the cache only ever holds the values of the current request.
 *
 * Not observed, by design: the last course access (written by require_login() without an event; the value read
 * is the stored one, which can already include the current visit) and changes written to the database directly
 * without an event (no Moodle API does that for the data read here).
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_observer {
    /**
     * Forget the values read so far.
     *
     * @param \core\event\base $event Any of the observed events.
     */
    public static function values_changed(\core\event\base $event): void {
        \cache::make('availability_usercriteria', 'values')->purge();
    }

    /**
     * Forget the custom profile field definitions and the values read so far.
     *
     * @param \core\event\base $event A profile field event.
     */
    public static function fields_changed(\core\event\base $event): void {
        \cache::make('availability_usercriteria', 'fields')->purge();
        \cache::make('availability_usercriteria', 'values')->purge();
    }

    /**
     * Remove the stored usage data and lists of a deleted course.
     *
     * @param \core\event\course_deleted $event The event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records(usage::TABLE, ['courseid' => $event->objectid]);
        $DB->delete_records(relative::TABLE, ['courseid' => $event->objectid]);
    }

    /**
     * Remove the stored usage data of a deleted person.
     *
     * @param \core\event\user_deleted $event The event.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records(usage::TABLE, ['userid' => $event->objectid]);
    }
}
