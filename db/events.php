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

/**
 * Event observers of availability_usercriteria: they keep the request cache fresh.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [];
$values = [
    // Profile and identity.
    '\core\event\user_updated',
    '\core\event\user_loggedin',
    // Roles.
    '\core\event\role_assigned',
    '\core\event\role_unassigned',
    // Enrolments.
    '\core\event\user_enrolment_created',
    '\core\event\user_enrolment_updated',
    '\core\event\user_enrolment_deleted',
    '\core\event\enrol_instance_updated',
    '\core\event\enrol_instance_deleted',
    // Completion.
    '\core\event\course_module_completion_updated',
    '\core\event\course_module_updated',
    // Grades.
    '\core\event\user_graded',
    '\core\event\grade_deleted',
    '\core\event\grade_item_updated',
    '\core\event\grade_item_deleted',
];
foreach ($values as $eventname) {
    $observers[] = [
        'eventname' => $eventname,
        'callback' => '\availability_usercriteria\local\cache_observer::values_changed',
        'internal' => true,
    ];
}
foreach (['created', 'updated', 'deleted'] as $change) {
    $observers[] = [
        'eventname' => '\core\event\user_info_field_' . $change,
        'callback' => '\availability_usercriteria\local\cache_observer::fields_changed',
        'internal' => true,
    ];
}
