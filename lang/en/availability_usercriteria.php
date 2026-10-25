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
 * English strings for availability_usercriteria.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Keep the keys in alphabetical order: moodle.Files.LangFilesOrdering enforces it.
$string['asnumber'] = '{$a} (as number)';
$string['bounds_both'] = 'both limits included';
$string['bounds_lower'] = 'lower limit included, upper excluded';
$string['bounds_none'] = 'both limits excluded';
$string['bounds_upper'] = 'lower limit excluded, upper included';
$string['cachedef_values'] = 'Values read for one person during one page request';
$string['cat_activity'] = 'Login and access';
$string['cat_enrolment'] = 'Enrolment';
$string['cat_identity'] = 'Identity';
$string['cat_profile'] = 'Profile fields';
$string['cat_progress'] = 'Progress and grade';
$string['cat_role'] = 'Roles';
$string['desc'] = '{$a->feature} {$a->operator} {$a->operand}';
$string['desc_days'] = '{$a} days';
$string['desc_identity'] = 'Your user data meets a criterion set by the teacher';
$string['desc_none'] = '{$a->feature} {$a->operator}';
$string['desc_not'] = 'NOT ({$a})';
$string['desc_pair'] = '{$a->first} and {$a->second} ({$a->bounds})';
$string['description'] = 'Restrict access by characteristics of the person: identity, profile fields, roles, enrolment, activity, progress and grade.';
$string['editor_bounds'] = 'Limits';
$string['editor_choose'] = 'Choose...';
$string['editor_courseinfo'] = 'Read in this course.';
$string['editor_days'] = 'days';
$string['editor_feature'] = 'Characteristic';
$string['editor_operator'] = 'Operator';
$string['editor_siteinfo'] = 'Read for the whole site.';
$string['editor_value'] = 'Value';
$string['editor_value2'] = 'Upper value';
$string['error_backwardrange'] = 'The lower value must not be greater than the upper value.';
$string['error_invaliddays'] = 'Enter a whole number of days of at least 1.';
$string['error_invalidvalue'] = 'Enter a valid value for the user criteria condition.';
$string['error_selectfeature'] = 'Choose a characteristic for the user criteria condition.';
$string['error_selectoperator'] = 'Choose an operator for the user criteria condition.';
$string['error_selectvalue'] = 'Choose at least one value for the user criteria condition.';
$string['feature_completed_count'] = 'Number of successfully completed activities';
$string['feature_course_grade'] = 'Course grade (%)';
$string['feature_course_lastaccess'] = 'Last course access';
$string['feature_enrol_method'] = 'Enrolment method';
$string['feature_enrol_start'] = 'Enrolment start';
$string['feature_finished_count'] = 'Number of finished activities (including not passed)';
$string['feature_role_course_direct'] = 'Role assigned directly in this course';
$string['feature_role_course_effective'] = 'Effective role in this course (including inherited)';
$string['feature_role_system'] = 'System role';
$string['feature_site_lastlogin'] = 'Last site login';
$string['feature_user_address'] = 'Address';
$string['feature_user_alternatename'] = 'Alternate name';
$string['feature_user_city'] = 'City/town';
$string['feature_user_country'] = 'Country code';
$string['feature_user_department'] = 'Department';
$string['feature_user_email'] = 'Email address';
$string['feature_user_firstname'] = 'First name';
$string['feature_user_id'] = 'User ID';
$string['feature_user_idnumber'] = 'ID number';
$string['feature_user_institution'] = 'Institution';
$string['feature_user_lang'] = 'Preferred language';
$string['feature_user_lastname'] = 'Last name';
$string['feature_user_middlename'] = 'Middle name';
$string['feature_user_phone1'] = 'Phone';
$string['feature_user_phone2'] = 'Mobile phone';
$string['feature_user_username'] = 'Username';
$string['hint_completed_count'] = 'Number of activities of this course the person has completed or passed. Counted are activities with completion tracking that the teacher made visible, including those available but not shown on the course page. Not counted: hidden activities, activities in hidden sections, activities being deleted. Access restrictions of the activities are not checked: an activity the person cannot open yet is still counted, and a restricted activity counts itself.';
$string['hint_course_grade'] = 'Course total as percent of its range (0-100), not rounded; a scale counts from its first (0) to its last item (100). Overrides count, hidden and locked grades too. Without a grade, or while Moodle still has to recalculate the grades, the condition is not met; use "is not available" to ask for a missing grade.';
$string['hint_course_lastaccess'] = 'The last time Moodle recorded this person in the course (any page of the course). Not a measure of learning activity, and not taken from the logs.';
$string['hint_enrol_start'] = 'Start of an active enrolment, or its creation time if no start is set. With several enrolments one active enrolment is enough. Without an active enrolment the value is not available. Time in your time zone.';
$string['hint_finished_count'] = 'Like the number of completed activities, but "not passed" also counts: complete, passed or not passed. Same activities: tracked and visible, access restrictions not checked.';
$string['hint_site_lastlogin'] = 'Most recent login. "Is not available" means never logged in.';
$string['op_after'] = 'is after';
$string['op_before'] = 'is before';
$string['op_between'] = 'is between';
$string['op_contains'] = 'contains';
$string['op_endswith'] = 'ends with';
$string['op_eq'] = 'is equal to';
$string['op_gt'] = 'is greater than';
$string['op_gte'] = 'is at least';
$string['op_in'] = 'is one of';
$string['op_inlast'] = 'is within the last';
$string['op_lt'] = 'is less than';
$string['op_lte'] = 'is at most';
$string['op_missing'] = 'is not available';
$string['op_ne'] = 'is not equal to';
$string['op_notcontains'] = 'does not contain';
$string['op_notin'] = 'is none of';
$string['op_olderthan'] = 'is longer ago than';
$string['op_present'] = 'is available';
$string['op_startswith'] = 'starts with';
$string['pluginname'] = 'User criteria';
$string['privacy:metadata'] = 'The User criteria plugin has no tables of its own and stores no data about the people it evaluates; it only reads existing Moodle data at the moment access is checked. A restriction rule, however, is course configuration and can contain identifying values chosen by a teacher (user ID, username, name, email, phone, address). Moodle stores such rules in the activity or section settings and includes them in course backups.';
$string['title'] = 'User criteria';
