@availability @availability_usercriteria @javascript
Feature: availability_usercriteria
  In order to restrict an activity by characteristics of the person
  As a teacher
  I need to set up and save a user criteria restriction, and students must be let in or kept out accordingly

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                | city       | timezone      |
      | teacher1 | Teacher   | 1        | teacher1@example.com | Wuppertal  | Europe/Berlin |
      | student1 | Student   | 1        | student1@example.com | Wuppertal  | Europe/Berlin |
      | student2 | Student   | 2        | student2@example.com | Berlin     | Europe/Berlin |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activity" exists:
      | activity | page     |
      | course   | C1       |
      | name     | Page 1   |
      | content  | Secret page content |

  @accessibility
  Scenario: A user criteria restriction is saved and reloaded unchanged
    When I am on the "Page 1" "page activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "User criteria" "button" in the "Add restriction..." "dialogue"
    And I set the field "Characteristic" to "Course grade (%)"
    And I set the field "Operator" to "is at least"
    And I set the field "Value" to "50"
    # Checked on the restriction editor only: other fields of Moodle's settings form are not this plugin's.
    And the ".availability-field" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    And I press "Save and display"
    And I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    Then the field "Characteristic" matches value "Course grade (%)"
    And the field "Operator" matches value "is at least"
    And the field "Value" matches value "50"

  Scenario: A restriction set up in the editor lets in exactly the matching student
    Given I am on the "Page 1" "page activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "User criteria" "button" in the "Add restriction..." "dialogue"
    And I set the field "Characteristic" to "City/town"
    And I set the field "Operator" to "is equal to"
    And I set the field "Value" to "Wuppertal"
    And I press "Save and return to course"
    And I log out
    When I am on the "Page 1" "page activity" page logged in as student1
    Then I should see "Secret page content"
    And I log out
    And I am on the "C1" "Course" page logged in as student2
    And I should see "Not available unless: City/town is equal to Wuppertal"
    And I am on the "Page 1" "page activity" page
    And I should not see "Secret page content"

  Scenario: The student is told which restriction applies, without values
    Given the following "activity" exists:
      | activity     | page                                                                                                |
      | course       | C1                                                                                                  |
      | name         | Page 2                                                                                              |
      | availability | {"op":"&","c":[{"type":"usercriteria","v":1,"feature":"course_grade","op":"gte","val":50}],"showc":[true]} |
    When I am on the "C1" "Course" page logged in as student1
    Then I should see "Not available unless: Course grade (%) is at least 50"

  Scenario: A restriction naming a person does not show the value to students
    Given the following "activity" exists:
      | activity     | page                                                                                                                     |
      | course       | C1                                                                                                                       |
      | name         | Page 3                                                                                                                   |
      | availability | {"op":"&","c":[{"type":"usercriteria","v":1,"feature":"user_email","op":"eq","val":"student1@example.com"}],"showc":[true]} |
    When I am on the "C1" "Course" page logged in as student2
    Then I should see "Your user data meets a criterion set by the teacher"
    And I should not see "student1@example.com"

  Scenario: Dates around the summer time changes are shown in the teacher's time zone
    # 2026-03-29 01:30 UTC is 03:30 in Berlin, right after the gap; 2026-10-25 00:30 UTC is the first 02:30.
    Given the following "activity" exists:
      | activity     | page                                                                                                                                              |
      | course       | C1                                                                                                                                                |
      | name         | Page 4                                                                                                                                            |
      | availability | {"op":"&","c":[{"type":"usercriteria","v":1,"feature":"site_lastlogin","op":"between","val":1774747800,"val2":1792888200,"inc":"both"}],"showc":[true]} |
    When I am on the "Page 4" "page activity editing" page logged in as teacher1
    And I expand all fieldsets
    Then the field "Value" matches value "2026-03-29T03:30"
    And the field "Upper value" matches value "2026-10-25T02:30"

  Scenario: An activity hidden entirely while the restriction is not met
    Given the following "activity" exists:
      | activity     | page                                                                                                                 |
      | course       | C1                                                                                                                   |
      | name         | Page 5                                                                                                               |
      | availability | {"op":"&","c":[{"type":"usercriteria","v":1,"feature":"user_city","op":"eq","val":"Wuppertal"}],"showc":[false]} |
    When I am on the "C1" "Course" page logged in as student2
    Then I should not see "Page 5"
    And I log out
    And I am on the "C1" "Course" page logged in as student1
    And I should see "Page 5"

  Scenario: A relative criterion and the usage time are offered and saved
    When I am on the "Page 1" "page activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "User criteria" "button" in the "Add restriction..." "dialogue"
    And I set the field "Characteristic" to "Usage time in the course (hours, estimated)"
    And I should see "Estimated from the standard log"
    And I set the field "Characteristic" to "Course grade (%)"
    And I set the field "Operator" to "is among the best"
    And I set the field "Value" to "3"
    And I should see "Compared with the participants of the course"
    And I press "Save and display"
    And I am on the "Page 1" "page activity editing" page
    And I expand all fieldsets
    Then the field "Operator" matches value "is among the best"
    And the field "Value" matches value "3"

  Scenario: The administrator sets the participant roles, by default the student role
    When I log in as "admin"
    And I navigate to "Plugins > Availability restrictions > User criteria" in site administration
    Then I should see "Participant roles"
    And the field "Student" matches value "1"
    And the field "Teacher" matches value ""

  Scenario: A course grade condition in a course with a gradebook does not break the course page
    # Regression: a course page does not load gradelib; the grade reader must load it itself.
    Given the following "grade items" exist:
      | itemname | course | grademax |
      | Essay    | C1     | 100      |
    And the following "grade grades" exist:
      | gradeitem | user     | grade |
      | Essay     | student1 | 80    |
    And the following "activity" exists:
      | activity     | page                                                                                                |
      | course       | C1                                                                                                  |
      | name         | Page 6                                                                                              |
      | content      | Graded page content                                                                                 |
      | availability | {"op":"&","c":[{"type":"usercriteria","v":1,"feature":"course_grade","op":"gte","val":50}],"showc":[true]} |
    # Opening the grader report recalculates the course total, as on a real site.
    And I am on the "C1" "grades > Grader report > View" page logged in as "teacher1"
    And I log out
    When I am on the "Page 6" "page activity" page logged in as student1
    Then I should see "Graded page content"
    And I log out
    And I am on the "C1" "Course" page logged in as student2
    And I should see "Not available unless: Course grade (%) is at least 50"
