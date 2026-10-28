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

namespace availability_usercriteria\task;

/**
 * Computes the lists for relative criteria of every course that uses them.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_distributions extends \core\task\scheduled_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_update_distributions', 'availability_usercriteria');
    }

    /**
     * Compute the lists.
     */
    public function execute() {
        $now = \core\di::get(\core\clock::class)->time();
        $courseids = \availability_usercriteria\local\relative::courses_in_use();
        foreach ($courseids as $courseid) {
            \availability_usercriteria\local\relative::compute($courseid, $now);
        }
        mtrace('availability_usercriteria: lists of ' . count($courseids) . ' courses computed.');
    }
}
