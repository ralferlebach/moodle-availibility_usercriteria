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

namespace availability_usercriteria\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for availability_usercriteria.
 *
 * Personal data of this plugin: the estimated usage time and first logged access per person and course
 * (table availability_usercriteria_use), kept in the course context. The lists for relative criteria contain
 * values only, without any reference to persons. Restriction rules themselves are course configuration stored by
 * Moodle in the activity and section settings (see docs/Datenschutz.md).
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Table with personal data. */
    private const TABLE = 'availability_usercriteria_use';

    /**
     * Describe the stored data.
     *
     * @param collection $collection Collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'userid' => 'privacy:metadata:usage:userid',
            'courseid' => 'privacy:metadata:usage:courseid',
            'seconds' => 'privacy:metadata:usage:seconds',
            'sessions' => 'privacy:metadata:usage:sessions',
            'firstaccess' => 'privacy:metadata:usage:firstaccess',
            'lastevent' => 'privacy:metadata:usage:lastevent',
            'timemodified' => 'privacy:metadata:usage:timemodified',
        ], 'privacy:metadata:usage');
        return $collection;
    }

    /**
     * Course contexts with data of a person.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {" . self::TABLE . "} u
               JOIN {context} ctx ON ctx.instanceid = u.courseid AND ctx.contextlevel = :courselevel
              WHERE u.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * People with data in a context.
     *
     * @param userlist $userlist List to fill.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {" . self::TABLE . "} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Export the data of a person.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $row = $DB->get_record(self::TABLE, ['userid' => $userid, 'courseid' => $context->instanceid]);
            if (!$row) {
                continue;
            }
            writer::with_context($context)->export_data([get_string('privacy:path', 'availability_usercriteria')], (object) [
                'usagehours' => round((int) $row->seconds / HOURSECS, 2),
                'sessions' => (int) $row->sessions,
                'firstaccess' => $row->firstaccess ? transform::datetime($row->firstaccess) : null,
                'lastevent' => $row->lastevent ? transform::datetime($row->lastevent) : null,
                'timemodified' => transform::datetime($row->timemodified),
            ]);
        }
    }

    /**
     * Delete the data of everybody in a context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_course) {
            $DB->delete_records(self::TABLE, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete the data of one person in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records(self::TABLE, ['userid' => $userid, 'courseid' => $context->instanceid]);
            }
        }
    }

    /**
     * Delete the data of several people in one context.
     *
     * @param approved_userlist $userlist Approved people.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            self::TABLE,
            "courseid = :courseid AND userid $insql",
            $params + ['courseid' => $context->instanceid]
        );
    }
}
