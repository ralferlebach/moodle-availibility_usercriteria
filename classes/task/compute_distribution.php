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
 * Computes the lists for relative criteria of one course that has none yet, or only an outdated one.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compute_distribution extends \core\task\adhoc_task {
    /**
     * Compute the lists of the course in the custom data.
     */
    public function execute() {
        $data = $this->get_custom_data();
        \availability_usercriteria\local\relative::compute(
            (int) $data->courseid,
            \core\di::get(\core\clock::class)->time()
        );
    }
}
