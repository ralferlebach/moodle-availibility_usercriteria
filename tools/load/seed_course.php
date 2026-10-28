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
 * Seed a course for the parallel load test (developer tool, not shipped).
 *
 *   php tools/load/seed_course.php --moodle=/path/to/moodle --students=50 --activities=100 [--plain]
 *
 * Creates a course with the given number of pages, each restricted by nine user criteria conditions (one per data
 * source, including a relative one), students with the password "Load!2345" and grades, then runs both tasks of
 * the plugin. With --plain the pages are not restricted (baseline). Prints the course id and the usernames.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$options = getopt('', ['moodle:', 'students:', 'activities:', 'plain']);
$root = rtrim($options['moodle'] ?? '', '/');
require($root . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');

$students = (int) ($options['students'] ?? 50);
$activities = (int) ($options['activities'] ?? 100);
$plain = isset($options['plain']);
\core\session\manager::set_user(get_admin());
$generator = new testing_data_generator();
$clock = \core\di::get(\core\clock::class);

$suffix = $clock->time();
$course = $generator->create_course(['fullname' => 'Load ' . $suffix, 'shortname' => 'load' . $suffix,
    'enablecompletion' => 1]);
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$item = new grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Load', 'grademax' => 100,
    'grademin' => 0], false);
$item->insert();
$names = [];
for ($i = 1; $i <= $students; $i++) {
    $user = $generator->create_user(['username' => 'load' . $suffix . '_' . $i, 'password' => 'Load!2345',
        'city' => $i % 2 ? 'Wuppertal' : 'Berlin']);
    $generator->enrol_user($user->id, $course->id, $studentrole, 'manual');
    $item->update_final_grade($user->id, ($i * 37) % 100);
    $names[] = $user->username;
}
grade_regrade_final_grades($course->id);

$conditions = [
    ['feature' => 'user_city', 'op' => 'present'],
    ['feature' => 'role_course_effective', 'op' => 'in', 'val' => [(string) $studentrole]],
    ['feature' => 'enrol_method', 'op' => 'in', 'val' => ['manual']],
    ['feature' => 'enrol_start', 'op' => 'present'],
    ['feature' => 'course_lastaccess', 'op' => 'present'],
    ['feature' => 'completed_count', 'op' => 'gte', 'val' => 0],
    ['feature' => 'course_grade', 'op' => 'gte', 'val' => 0],
    ['feature' => 'usage_time', 'op' => 'gte', 'val' => 0],
    ['feature' => 'course_grade', 'op' => 'toppct', 'val' => 100],
];
$children = array_map(fn($c) => $c + ['type' => 'usercriteria', 'v' => 1], $conditions);
$availability = json_encode(['op' => '&', 'c' => $children, 'showc' => array_fill(0, count($children), true)]);
for ($i = 1; $i <= $activities; $i++) {
    $generator->create_module('page', ['course' => $course->id, 'name' => 'Page ' . $i,
        'completion' => COMPLETION_TRACKING_MANUAL] + ($plain ? [] : ['availability' => $availability]));
}
\availability_usercriteria\local\usage::process();
\availability_usercriteria\local\relative::compute((int) $course->id, $clock->time());
echo 'COURSEID=' . $course->id . "\n";
echo 'USERS=' . implode(',', $names) . "\n";
