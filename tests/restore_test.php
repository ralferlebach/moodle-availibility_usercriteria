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

namespace availability_usercriteria;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/auc_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore: rules survive, ids of the source site are mapped to the target site.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_usercriteria\condition
 */
final class restore_test extends auc_testcase {
    /**
     * A course with a rule is backed up and restored into a new course; the rule is kept and evaluated alike.
     */
    public function test_backup_and_restore(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;
        $course = $this->getDataGenerator()->create_course();
        $teacher = (string) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Restricted']);
        $this->restrict($page->cmid, $course, [['feature' => 'role_course_direct', 'op' => 'in', 'val' => [$teacher]],
            ['feature' => 'course_grade', 'op' => 'best', 'val' => 3]], '|');

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(false);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newid = \restore_dbops::create_new_course('Restored', 'R1', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $cm = $DB->get_record('course_modules', ['course' => $newid, 'module' => $DB->get_field(
            'modules',
            'id',
            ['name' => 'page']
        )], '*', MUST_EXIST);
        $tree = json_decode($cm->availability);
        $this->assertSame('usercriteria', $tree->c[0]->type);
        $this->assertSame([$teacher], $tree->c[0]->val, 'Same site: the role keeps its id');
        $this->assertSame('best', $tree->c[1]->op);
    }

    /**
     * Role ids and a user id are mapped to the ids the restore chose; unknown ones are kept and reported.
     */
    public function test_ids_are_mapped(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $restoreid = 'auc' . random_string(8);
        \restore_controller_dbops::create_restore_temp_tables($restoreid);
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $person = $this->getDataGenerator()->create_user();
        \restore_dbops::set_backup_ids_record($restoreid, 'role', 9001, $student);
        \restore_dbops::set_backup_ids_record($restoreid, 'user', 4242, $person->id);
        $logger = new \core_backup_html_logger(\backup::LOG_DEBUG);

        $roles = $this->cond(['feature' => 'role_course_direct', 'op' => 'in', 'val' => ['9001', (string) $teacher, '9999']]);
        $this->assertTrue($roles->update_after_restore($restoreid, $course->id, $logger, 'Page'));
        $this->assertSame([(string) $student, (string) $teacher, '9999'], $roles->save()->val);
        $this->assertStringContainsString('role that was not restored', $logger->get_html());

        $user = $this->cond(['feature' => 'user_id', 'op' => 'eq', 'val' => 4242]);
        $this->assertTrue($user->update_after_restore($restoreid, $course->id, $logger, 'Page'));
        $this->assertSame((int) $person->id, $user->save()->val);

        $range = $this->cond(['feature' => 'user_id', 'op' => 'gt', 'val' => 4242]);
        $this->assertFalse($range->update_after_restore($restoreid, $course->id, $logger, 'Page'));
        $this->assertStringContainsString('not mapped', $logger->get_html());

        $other = $this->cond(['feature' => 'course_grade', 'op' => 'gte', 'val' => 50]);
        $this->assertFalse($other->update_after_restore($restoreid, $course->id, $logger, 'Page'));
        \restore_controller_dbops::drop_restore_temp_tables($restoreid);
    }
}
