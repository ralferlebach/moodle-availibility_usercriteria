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
$string['desc_count'] = '{$a} participants';
$string['desc_days'] = '{$a} days';
$string['desc_identity'] = 'Your user data meets a criterion set by the teacher';
$string['desc_none'] = '{$a->feature} {$a->operator}';
$string['desc_not'] = 'NOT ({$a})';
$string['desc_pair'] = '{$a->first} and {$a->second} ({$a->bounds})';
$string['desc_percent'] = '{$a} % of the participants';
$string['description'] = 'Restrict access by characteristics of the person: identity, profile fields, roles, enrolment, activity, progress and grade.';
$string['diag'] = '(Cannot be evaluated here: {$a})';
$string['diag_nocompletion'] = 'completion tracking is off in this course.';
$string['diag_nocoursecompletion'] = 'this course has no course completion criteria.';
$string['diag_nolist'] = 'the comparison values of the participants are being computed (scheduled task).';
$string['diag_nolog'] = 'the standard log store is not enabled.';
$string['diag_noparticipantroles'] = 'no participant roles are set in the plugin settings.';
$string['diag_notprocessed'] = 'the log has not been processed yet (scheduled task).';
$string['diag_unknownfeature'] = 'the characteristic does not exist on this site, for example a deleted profile field.';
$string['diag_unknownoperator'] = 'the operator does not fit the characteristic.';
$string['editor_bounds'] = 'Limits';
$string['editor_choose'] = 'Choose...';
$string['editor_count'] = 'participants';
$string['editor_courseinfo'] = 'Read in this course.';
$string['editor_days'] = 'days';
$string['editor_feature'] = 'Characteristic';
$string['editor_operator'] = 'Operator';
$string['editor_percent'] = '% of the participants';
$string['editor_relativeinfo'] = 'Compared with the participants of the course (roles set by the administrator) as of the last hourly computation; ties share the better position. Your own current value is used; a change since the last computation can only delay a release.';
$string['editor_siteinfo'] = 'Read for the whole site.';
$string['editor_value'] = 'Value';
$string['editor_value2'] = 'Upper value';
$string['error_backwardrange'] = 'The lower value must not be greater than the upper value.';
$string['error_invalidcount'] = 'Enter a whole number of participants of at least 1.';
$string['error_invaliddays'] = 'Enter a whole number of days of at least 1.';
$string['error_invalidpercent'] = 'Enter a percentage above 0 and at most 100.';
$string['error_invalidvalue'] = 'Enter a valid value for the user criteria condition.';
$string['error_selectfeature'] = 'Choose a characteristic for the user criteria condition.';
$string['error_selectoperator'] = 'Choose an operator for the user criteria condition.';
$string['error_selectvalue'] = 'Choose at least one value for the user criteria condition.';
$string['feature_completed_count'] = 'Number of successfully completed activities';
$string['feature_completion_duration'] = 'Time from first access to course completion (days)';
$string['feature_course_grade'] = 'Course grade (%)';
$string['feature_course_lastaccess'] = 'Last course access';
$string['feature_enrol_method'] = 'Enrolment method';
$string['feature_enrol_start'] = 'Enrolment start';
$string['feature_finished_count'] = 'Number of finished activities (including not passed)';
$string['feature_role_course_direct'] = 'Role assigned directly in this course';
$string['feature_role_course_effective'] = 'Effective role in this course (including inherited)';
$string['feature_role_system'] = 'System role';
$string['feature_site_lastlogin'] = 'Last site login';
$string['feature_usage_time'] = 'Usage time in the course (hours, estimated)';
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
$string['hint_completion_duration'] = 'Days from the first access to the course found in the standard log to the completion of the course. Not completed (yet): the value is not available. Needs course completion with criteria. Accesses removed from the log before they were processed are not known.';
$string['hint_course_grade'] = 'Course total as percent of its range (0-100), not rounded; a scale counts from its first (0) to its last item (100). Overrides count, hidden and locked grades too. Without a grade, or while Moodle still has to recalculate the grades, the condition is not met; use "is not available" to ask for a missing grade.';
$string['hint_course_lastaccess'] = 'The last time Moodle recorded this person in the course (any page of the course). Not a measure of learning activity, and not taken from the logs.';
$string['hint_enrol_start'] = 'Start of an active enrolment, or its creation time if no start is set. With several enrolments one active enrolment is enough. Without an active enrolment the value is not available. Time in your time zone.';
$string['hint_finished_count'] = 'Like the number of completed activities, but "not passed" also counts: complete, passed or not passed. Same activities: tracked and visible, access restrictions not checked.';
$string['hint_site_lastlogin'] = 'Most recent login. "Is not available" means never logged in.';
$string['hint_usage_time'] = 'Estimated from the standard log: events at most 30 minutes apart form a session and the time between them counts; time after the last click of a session does not. A lower estimate, updated every 10 minutes. Without logged events: 0 hours.';
$string['op_after'] = 'is after';
$string['op_before'] = 'is before';
$string['op_best'] = 'is among the best';
$string['op_between'] = 'is between';
$string['op_bottompct'] = 'is among the bottom';
$string['op_contains'] = 'contains';
$string['op_endswith'] = 'ends with';
$string['op_eq'] = 'is equal to';
$string['op_gt'] = 'is greater than';
$string['op_gte'] = 'is at least';
$string['op_in'] = 'is one of';
$string['op_inlast'] = 'is within the last';
$string['op_lt'] = 'is less than';
$string['op_lte'] = 'is at most';
$string['op_max'] = 'is the highest of the participants';
$string['op_min'] = 'is the lowest of the participants';
$string['op_missing'] = 'is not available';
$string['op_ne'] = 'is not equal to';
$string['op_notcontains'] = 'does not contain';
$string['op_notin'] = 'is none of';
$string['op_olderthan'] = 'is longer ago than';
$string['op_present'] = 'is available';
$string['op_startswith'] = 'starts with';
$string['op_toppct'] = 'is among the top';
$string['op_worst'] = 'is among the lowest';
$string['participantroles'] = 'Participant roles';
$string['participantroles_desc'] = 'Relative criteria compare a person with the participants of the course: people with an active enrolment and one of these roles assigned in the course.';
$string['pluginname'] = 'User criteria';
$string['privacy:metadata:usage'] = 'Estimated usage time and first logged access per person and course, computed from the standard log for restriction rules.';
$string['privacy:metadata:usage:courseid'] = 'The course.';
$string['privacy:metadata:usage:firstaccess'] = 'Time of the first logged access to the course.';
$string['privacy:metadata:usage:lastevent'] = 'Time of the last processed event in the course.';
$string['privacy:metadata:usage:seconds'] = 'Estimated usage time in seconds.';
$string['privacy:metadata:usage:sessions'] = 'Number of sessions.';
$string['privacy:metadata:usage:timemodified'] = 'Time of the last update.';
$string['privacy:metadata:usage:userid'] = 'The person.';
$string['privacy:path'] = 'Usage time';
$string['task_update_distributions'] = 'Comparison values for relative user criteria';
$string['task_update_usage'] = 'Usage time from the standard log (user criteria)';
$string['title'] = 'User criteria';
