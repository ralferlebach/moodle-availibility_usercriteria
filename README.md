moodle-availability_usercriteria
================================

Restrict access to activities and sections by characteristics of the person: identity, profile fields,
roles, enrolment, login and course access, usage time, progress and course grade - also relative to the other
participants of the course. One small availability condition plugin
for Moodle's *Restrict access* - no reports, no dashboards, no enrolment.

**Status:** 0.4.0, `MATURITY_ALPHA`. Not yet accepted for production. What has actually been run, on which
Moodle and database versions, is listed in the [test report](docs/Testbericht-0.4.0.md); what is
implemented and what is not in the [requirements matrix](docs/Anforderungsmatrix.md).


Requirements
------------

Moodle 4.5 to 5.3. PHP as required by the Moodle version; tested with PHP 8.3 only.


Installation
------------

Install the plugin like any other plugin to folder

    availability/condition/usercriteria

(Moodle 5.1 and later: `public/availability/condition/usercriteria`).

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.


Usage
-----

Open the settings of an activity or section, expand *Restrict access*, choose *Add restriction...* and
then *User criteria*. A condition is one *characteristic*, one *operator* and a *value*. Moodle's own
restriction sets combine conditions with AND, OR and NOT, also with Moodle's own conditions.

| Category | Characteristics |
|---|---|
| Identity | user ID, username, first name, last name |
| Profile fields | ID number, institution, department, city, country, language, email, phone, mobile phone, address, middle name, alternate name, custom profile fields (text fields optionally compared as numbers) |
| Roles | role assigned directly in the course, effective role in the course, system role |
| Enrolment | enrolment method, enrolment start |
| Login and access | last site login, last course access, usage time in the course (estimated, hours) |
| Progress and grade | completed activities, finished activities (also "not passed"), course grade in percent, time from first access to course completion (days) |

Operators depend on the type: text (equals, contains, starts/ends with ...), number (=, >, >=, ..., between with
selectable limits), time (before, after, between, within the last X days, longer ago than X days) and selection
(is one of, is none of). *Is not available* and *is available* ask explicitly for missing data.

Course grade, completed activities and usage time can also be compared with the other participants: *is among
the best / lowest N*, *is the highest / lowest*, *is among the top / bottom P %*.

### Semantics worth knowing

* **Missing data never releases anything**, also not when negated. Use *is not available* to ask for missing
  data on purpose. An ungraded person is missing, not zero.
* **Enrolments:** a criterion is met if at least one *active* enrolment meets it. Without an active enrolment
  the value is missing, so "is none of" is not met either.
* **Course grade:** the course total in percent, as Moodle's own grade condition computes it (overrides,
  hidden and locked grades, scales). Two deliberate differences, both closing rather than opening access: a
  total waiting for a regrade is not decided, and NOT with a missing grade does not release.
* **Completed activities:** tracked activities that are visible (not hidden, not in a hidden section, not
  being deleted). Access restrictions of the counted activities are not checked.
* **Last course access** comes from Moodle's last-access record, not from the logs.
* **Usage time** is estimated from the standard log: events at most 30 minutes apart form a session and the time
  between them counts; time after the last click of a session does not (a lower estimate). A scheduled task
  processes new log entries every 10 minutes; pages never read the log.
* **Time to completion** runs from the first course access found in the standard log to the course completion;
  not completed means the value is missing.
* **Relative criteria** compare with the participants: active enrolment and a participant role (plugin setting,
  default: student). The values of the others are computed hourly and stored without reference to persons; ties
  share the better position; the person's own value is current. A list older than 3 hours decides nothing.
* **Time** is always taken from Moodle's clock API (`\core\clock`), so tests can freeze and move it.
* Restriction messages never show values of people; identity and contact criteria are not spelled out at all.

Details: [permissions](docs/Berechtigungen.md), [privacy](docs/Datenschutz.md), [technical design and cache
validity](docs/Technisches_Blueprint.md).


Settings, capabilities, tasks and data
--------------------------------------

* Setting *Participant roles* (Site administration > Plugins > Availability restrictions > User criteria):
  who counts as a participant for relative criteria. Default: the student role.
* No capabilities of its own (editing restrictions is limited by Moodle).
* Scheduled tasks: *Usage time from the standard log* (every 10 minutes), *Comparison values for relative user
  criteria* (hourly, only courses that use them).
* Tables: estimated usage time and first logged access per person and course (personal data, exported and
  deleted through the Privacy API); sorted comparison values per course without reference to persons.
* Values read during access checks are kept in a request cache only; Moodle events about changed data clear it.
* Backup and restore: role ids and user ids in rules are mapped to the target site.


Development
-----------

[Environment and test setup](docs/environment-setup.md), [work plan](docs/Arbeitsplanung.md), specification:
[Lastenheft](docs/Lastenheft.md), [Pflichtenheft](docs/Pflichtenheft.md), [Technisches Blueprint](docs/Technisches_Blueprint.md).
Changes: [CHANGELOG](CHANGELOG.md). Load test: `tools/load/` (repository only). The developer tools (`tools/`) are part of the repository, not of the
release package.


Licence
-------

GNU GPL v3 or later, matching Moodle itself.
