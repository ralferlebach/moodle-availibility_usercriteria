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
 * Entry point for reading a characteristic; dispatches to the reader of its source.
 *
 * Results are kept in a request cache so rendering a course page with many restricted activities reads
 * each characteristic once per person and course. Data that depends on the time of evaluation (enrolments) is
 * not cached as a result: its reader caches the raw rows and works out the result from the time it is given.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader {
    /**
     * Read one characteristic for one person.
     *
     * @param array $feature Catalog definition.
     * @param int $userid User id.
     * @param \stdClass $course The course the restricted item belongs to.
     * @param int $now Current time.
     * @return value
     */
    public static function read(array $feature, int $userid, \stdClass $course, int $now): value {
        if ($feature['source'] === 'enrolment') {
            return reader\enrolment::read($feature['key'], $userid, $course, $now);
        }
        $courseid = $feature['scope'] === catalog::COURSE ? (int) $course->id : 0;
        $cache = \cache::make('availability_usercriteria', 'values');
        $cachekey = md5($feature['key'] . '|' . $userid . '|' . $courseid);
        $stored = $cache->get($cachekey);
        if ($stored !== false) {
            return value::from_array($stored);
        }
        $value = self::fetch($feature, $userid, $course, $now);
        $cache->set($cachekey, $value->to_array());
        return $value;
    }

    /**
     * Forget everything read so far in this request.
     *
     * Moodle's own changes are picked up through the events observed by cache_observer; this is for changes
     * written directly to the database, in practice in tests.
     */
    public static function reset(): void {
        \cache::make('availability_usercriteria', 'values')->purge();
    }

    /**
     * Fetch without caching.
     *
     * @param array $feature Catalog definition.
     * @param int $userid User id.
     * @param \stdClass $course The course.
     * @param int $now Current time.
     * @return value
     */
    private static function fetch(array $feature, int $userid, \stdClass $course, int $now): value {
        switch ($feature['source']) {
            case 'user':
                return reader\user::read($feature['key'], $userid);
            case 'profile':
                return reader\profile::read($feature, $userid);
            case 'role':
                return reader\role::read($feature['key'], $userid, $course);
            case 'completion':
                return reader\completion::read($feature['key'], $userid, $course);
            case 'grade':
                return reader\grade::read($userid, $course);
            case 'activity':
                return reader\activity::read($userid, $course);
        }
        return value::unavailable();
    }
}
