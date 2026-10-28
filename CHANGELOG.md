# Changelog

## 0.4.0 - 2026-10-28 (alpha)

Open requirements of the Lastenheft/Pflichtenheft completed.

New characteristics and operators
- Usage time in the course (L-09): estimated from the standard log (sessions with pauses of at most 30 minutes;
  a lower estimate), aggregated incrementally by a scheduled task every 10 minutes; pages never read the log.
- Time from the first logged course access to course completion (L-10), in days.
- Relative criteria (L-11) for course grade, completed activities and usage time: among the best/lowest N, the
  highest/lowest, among the top/bottom P %. Compared with the participants of the course (active enrolment and a
  participant role); values of the others computed hourly and stored without reference to persons; ties share the
  better position; lists older than 3 hours decide nothing.
- Plugin setting "Participant roles" (default: student).

Fixes
- Course grade conditions broke the course page (HTTP 500, undefined GRADE_TYPE_VALUE) as soon as the course had a
  gradebook: a course page does not load gradelib. Present since 0.2.0, found by the new parallel load test;
  regression covered by a Behat scenario.
- Backup and restore: role ids and user ids (equal/not equal) in rules are mapped to the target site.

Other
- Teachers see why a rule cannot be evaluated in a course ("Cannot be evaluated here: ..."), never data of people.
- Privacy: full provider for the new usage table (export, deletion per person, list and course).
- Deleted courses and people take their stored data with them.
- Catalog definitions built once per request: evaluating 900 conditions on one page takes 0.04 s instead of 0.09 s.
- Tests: 230 PHPUnit tests (0.3.0: 208), 9 Behat scenarios (0.3.0: 5), parallel load test (tools/load).

## 0.3.0 - 2026-10-25 (alpha)

CI fix after the first GitHub run: the MariaDB service health check uses `mariadb-admin` (the 11.4 image has
no `mysqladmin`, so the Moodle 5.3 / MariaDB job could not start). Plugin code unchanged.

Rework after the re-audit of 0.2.0 (AUC_ReAudit_0.2.0). Not yet accepted for production.

Behaviour
- Cache freshness: an event observer (`db/events.php`, `classes/local/cache_observer.php`) clears the request
  cache when Moodle changes profile, roles, enrolments, completion or grades, so a change in the middle of a
  request is seen by the next check. Validity model per characteristic in docs/Technisches_Blueprint.md.
- Course grade: a course total of type scale is now a percentage of the scale, as in core (0.2.0: not decided).
  Verified against core's grade condition for values, ranges, overrides, hidden, locked, missing and scales;
  two deliberate differences remain (pending regrade, NOT with a missing grade), both fail closed.
- Editor hints explain which activities the completion counters count and how the course grade is computed.

Tests and gates
- 208 PHPUnit tests (0.2.0: 181), new: grade parity with core, completion population (hidden sections, stealth,
  locked, mutual dependency, roles), cache freshness within a request, permission matrix, editor reveals no data
  of people, privacy export/deletion of a person named in a rule, error messages without values, clock source
  proof with a frozen clock in 2001, performance with 10/100/500 restricted activities.
- Clock gate extended: dynamic calls (call_user_func, callables, variable functions), relative DateTime strings,
  editor JavaScript (Date.now, new Date(), ...), each with negative controls.
- Behat: 5 scenarios incl. real access for a matching and a non-matching student, values hidden from students,
  summer time display in the editor, axe accessibility check of the restriction editor.
- CI: PostgreSQL 17 and MariaDB 11.4 for Moodle 5.3 (0.2.0 used versions 5.3 does not install on), template
  job for four sibling plugins removed, editor JS job (time zone tests, YUI build check), explicit clock gate,
  link check of the release archive. phpcs: 0 errors, 0 warnings (CI runs with --max-warnings 0).

Package
- docs/ now ships (README links to it); session notes and prompt templates stay in the repository only.
  CHANGELOG moved to the top level; the template's docs/README.md removed.

Not implemented: usage time (L-09), time to completion (L-10), relative criteria (L-11).

## 0.2.0 - 2026-10-25 (alpha)

Rework after the audit of 0.1.0 (AUC_Nacharbeitsauftrag_0.2.0).

Time and cache
- All time comes from the Moodle clock API (`\core\di::get(\core\clock::class)`); no direct `time()`.
  `tests/clock_usage_test.php` enforces this for `classes/`, and tests use `mock_clock_with_frozen()`.
- Enrolment: only raw rows are cached per request; "active" is worked out at evaluation time, so limits that
  pass within one request are honoured.

Semantics (decisions, please confirm - see docs/Arbeitsplanung.md)
- Enrolment: a person without any active enrolment has no value (missing). "is one of / is none of" are then
  not met, also under NOT; use "is not available" explicitly. Start = validity start, else creation time.
- Completion: `completed_count` (complete + pass) and new `finished_count` (also complete-fail). Counted
  activities come from the course module information without the viewer (no circular evaluation).
- Grade: course total normalised like core availability_grade, no rounding; items that are not numeric or need
  an update are unavailable.
- Numbers: exact integer comparison, tolerance 1e-9 only for decimals. "between" has selectable bounds
  (both, lower, upper, none; `inc` in JSON, default "both" keeps 0.1.0 rules working).
- Text profile fields can opt in to numeric comparison (`profile_num:<shortname>`).
- More standard profile fields selectable; identity and contact features are never spelled out and are
  redacted in debug output.

Editor
- Date entry is converted with Intl and the user's time zone name (DST overlap: first occurrence, gap: the
  instant after the gap). Node test: `tests/js/form_tz_test.js` (13 checks). `yui/build` rebuilt.
- Role lists are limited to roles assignable at the relevant context level.

Quality
- 181 PHPUnit tests (0.1.0: 65): green on Moodle 4.5 (PostgreSQL 16, PHPUnit 9.6) and 5.3 (PostgreSQL 17,
  PHPUnit 11.5). Upgrade 0.1.0 -> 0.2.0 run on a 4.5 dev site.
- phpcs (moodle-cs): 0 errors; 22 line-length warnings, mostly in tests.
- CI workflows no longer install the plugin from GitHub with add-plugin; the checked-out `./plugin` is tested.
- Privacy: still no tables (null provider), text rewritten: rules in course configuration can contain
  identifying values and are part of course backups.

Not verified in this environment: Behat, GitHub Actions run, MariaDB / MySQL / MSSQL, Moodle 5.0-5.2,
browser-level E2E, measured performance at 100+ conditions, legal privacy review.
Deferred: usage duration, duration to completion, relative criteria (L-09..L-11).

## 0.1.0 - 2026-10-25 (alpha)

Also selectable: email, phone, mobile phone, address, middle name, alternate name (never spelled out in restriction messages).

First version. Characteristics: identity, standard and custom profile fields, roles (direct, effective,
system), enrolment method and start, last site login, last course access, number of completed activities,
course grade in percent. Typed operators, fail-closed handling of missing data, request-scoped cache,
YUI editor, German and English strings, 65 PHPUnit tests. Verified on Moodle 4.5 and 5.3.

Not yet: usage duration, duration to completion, relative criteria (phase 2), MariaDB and Moodle 5.0-5.2
verification, Behat run.
