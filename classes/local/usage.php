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
 * Estimated usage time and first logged access per person and course (L-09, L-10).
 *
 * Method, shown to teachers in the editor: the events a person caused in a course (standard log, origin web or
 * web service, not while logged in as somebody else) are taken in the order they were logged. Two consecutive
 * events at most SESSION_GAP seconds apart belong to one session, and the time between them counts. A longer
 * pause starts a new session. Time after the last event of a session is not counted, so the result is a lower
 * estimate: a single click counts nothing, reading a page for an hour without another click counts nothing.
 *
 * The log is never read while a page is rendered. A scheduled task processes the new log entries since its last
 * run (incrementally, by log id) and adds them up in a table; reading the value is one indexed row. The first
 * logged access is the earliest event the task has seen, which is the earliest event still in the log when the
 * person was first processed: events deleted by the log retention before that are not known.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /** @var int Longest pause, in seconds, within one session. */
    public const SESSION_GAP = 1800;

    /** @var string Table of the aggregated values. */
    public const TABLE = 'availability_usercriteria_use';

    /** @var int Log entries read per query. */
    private const BATCH = 20000;

    /**
     * Whether usage data is available at all: the standard log store writes and the task has run at least once.
     *
     * @return bool
     */
    public static function is_tracking(): bool {
        return self::log_enabled() && get_config('availability_usercriteria', 'usage_lastlogid') !== false;
    }

    /**
     * Whether the standard log store is enabled.
     *
     * @return bool
     */
    public static function log_enabled(): bool {
        $stores = explode(',', (string) get_config('tool_log', 'enabled_stores'));
        return in_array('logstore_standard', array_map('trim', $stores), true);
    }

    /**
     * Process new log entries.
     *
     * @param int $maxevents Upper limit of log entries to read in this call (the rest waits for the next run).
     * @return int Number of log entries read.
     */
    public static function process(int $maxevents = 500000): int {
        global $DB;
        if (!self::log_enabled()) {
            return 0;
        }
        $lastid = (int) get_config('availability_usercriteria', 'usage_lastlogid');
        $clock = \core\di::get(\core\clock::class);
        $read = 0;
        while ($read < $maxevents) {
            $events = $DB->get_records_select(
                'logstore_standard_log',
                'id > :lastid',
                ['lastid' => $lastid],
                'id ASC',
                'id, userid, courseid, timecreated, origin, realuserid, anonymous',
                0,
                min(self::BATCH, $maxevents - $read)
            );
            if (!$events) {
                break;
            }
            $pending = [];
            foreach ($events as $event) {
                $lastid = (int) $event->id;
                if (
                    (int) $event->userid <= 0 || (int) $event->courseid <= SITEID || !empty($event->realuserid)
                        || !empty($event->anonymous) || !in_array($event->origin, ['web', 'ws'], true)
                ) {
                    continue;
                }
                $pending[(int) $event->userid . '-' . (int) $event->courseid][] = (int) $event->timecreated;
            }
            self::apply($pending, $clock->time());
            $read += count($events);
            set_config('usage_lastlogid', $lastid, 'availability_usercriteria');
        }
        if (get_config('availability_usercriteria', 'usage_lastlogid') === false) {
            // An empty log: tracking has started all the same.
            set_config('usage_lastlogid', $lastid, 'availability_usercriteria');
        }
        return $read;
    }

    /**
     * Add the events of one batch to the stored values.
     *
     * @param array $pending "userid-courseid" => list of event times in log order.
     * @param int $now Current time.
     */
    private static function apply(array $pending, int $now): void {
        global $DB;
        if (!$pending) {
            return;
        }
        // Courses deleted and people deleted since the events were logged leave nothing behind.
        $courseids = $userids = [];
        foreach (array_keys($pending) as $pair) {
            [$userid, $courseid] = array_map('intval', explode('-', $pair));
            $userids[$userid] = $userid;
            $courseids[$courseid] = $courseid;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($courseids));
        $courses = $DB->get_fieldset_select('course', 'id', "id $insql", $params);
        [$insql, $params] = $DB->get_in_or_equal(array_values($userids));
        $users = $DB->get_fieldset_select('user', 'id', "deleted = 0 AND id $insql", $params);
        $courses = array_flip(array_map('intval', $courses));
        $users = array_flip(array_map('intval', $users));

        $transaction = $DB->start_delegated_transaction();
        foreach ($pending as $pair => $times) {
            [$userid, $courseid] = array_map('intval', explode('-', $pair));
            if (!isset($courses[$courseid]) || !isset($users[$userid])) {
                continue;
            }
            $row = $DB->get_record(self::TABLE, ['userid' => $userid, 'courseid' => $courseid]);
            if (!$row) {
                $row = (object) ['userid' => $userid, 'courseid' => $courseid, 'seconds' => 0, 'sessions' => 0,
                    'firstaccess' => 0, 'lastevent' => 0, 'timemodified' => 0];
            }
            foreach ($times as $time) {
                if ((int) $row->firstaccess === 0 || $time < (int) $row->firstaccess) {
                    $row->firstaccess = $time;
                }
                $gap = $time - (int) $row->lastevent;
                if ((int) $row->lastevent > 0 && $gap >= 0 && $gap <= self::SESSION_GAP) {
                    $row->seconds = (int) $row->seconds + $gap;
                } else if ($gap > 0 || (int) $row->lastevent === 0) {
                    $row->sessions = (int) $row->sessions + 1;
                }
                $row->lastevent = max((int) $row->lastevent, $time);
            }
            $row->timemodified = $now;
            if (empty($row->id)) {
                $DB->insert_record(self::TABLE, $row);
            } else {
                $DB->update_record(self::TABLE, $row);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * The stored row of a person in a course.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return \stdClass|null
     */
    public static function row(int $userid, int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid, 'courseid' => $courseid]) ?: null;
    }

    /**
     * Usage time in hours of several people in a course; people without logged events have 0.
     *
     * @param int[] $userids User ids.
     * @param int $courseid Course id.
     * @return float[] User id => hours.
     */
    public static function hours_many(array $userids, int $courseid): array {
        global $DB;
        $result = array_fill_keys($userids, 0.0);
        foreach (array_chunk($userids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'u');
            $rows = $DB->get_records_select_menu(
                self::TABLE,
                "courseid = :courseid AND userid $insql",
                $params + ['courseid' => $courseid],
                '',
                'userid, seconds'
            );
            foreach ($rows as $userid => $seconds) {
                $result[(int) $userid] = (int) $seconds / HOURSECS;
            }
        }
        return $result;
    }
}
