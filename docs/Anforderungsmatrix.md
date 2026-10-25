# Anforderungsmatrix – availability_usercriteria (AUC) 0.3.0

**Stand:** 25.10.2026. Bezug: [Lastenheft](Lastenheft.md), [Pflichtenheft](Pflichtenheft.md). Status:
**umgesetzt** = implementiert und durch die genannten Tests belegt; **teilweise** = mit genannter Einschränkung;
**nicht umgesetzt** = bewusst nicht enthalten.

## Fachliche Anforderungen

| ID | Anforderung | Status | Umsetzung / Einschränkung | Nachweis |
|---|---|---|---|---|
| L-01 | Einzelner Nutzer: ID, Nutzername, Vor-/Nachname | umgesetzt | ID numerisch (=, ≠, >, ≥, <, ≤, zwischen), Namen als Text (gleich, enthält, beginnt/endet …) | `condition_test::test_identity_and_profile`, `privacy_test` |
| L-02 | Standard- und benutzerdefinierte Profilfelder, feldtypgerecht | umgesetzt | Text, Menü, Checkbox, Datum; numerischer Vergleich für Textfelder als ausdrückliche Wahl (`profile_num:`) | `profile_test`, `condition_test::test_custom_profile_fields` |
| L-03 | Kurs- und Systemrolle | umgesetzt | direkt im Kurs, im Kurs wirksam (geerbt), Systemrolle getrennt | `condition_test::test_roles`, `access_test::test_roles` |
| L-04 | Einschreibedatum vor/nach/zwischen, klarer Zeitbezug | umgesetzt | Beginn = Gültigkeitsbeginn, sonst Anlagezeitpunkt; nur aktive Einschreibungen | `enrolment_test`, `clock_source_test` |
| L-05 | Einschreibemethode, auch bei mehreren Einschreibungen | umgesetzt | mindestens eine aktive Einschreibung; keine aktive → Wert fehlt | `enrolment_test` |
| L-06 | Letzter Site-Login und letzte Kursaktivität getrennt | teilweise | Kursaktivität = letzter Kurszugriff aus `user_lastaccess`, nicht aus den Logs (bestätigt) | `condition_test::test_login_and_course_access`, `clock_source_test` |
| L-07 | Anzahl abgeschlossener Kursaktivitäten | umgesetzt | zwei Zähler: abgeschlossen (inkl. bestanden) und beendet (zusätzlich nicht bestanden); Zählmenge siehe unten | `completion_test` |
| L-08 | Kursnote mit =, ≠, >, ≥, <, ≤, zwischen; fehlende Noten | umgesetzt | Kursgesamtbewertung in % wie Core; zwei bewusste Abweichungen siehe unten | `grade_test`, `grade_parity_test` |
| L-09 | Nutzungsdauer im Kurs (geschätzt) | **nicht umgesetzt** | Soll-Anforderung; vorgesehen frühestens 0.4.x, nur mit externem Datenlieferanten (z. B. block_dedication) | – |
| L-10 | Dauer vom Kursstart bis Kursabschluss | **nicht umgesetzt** | Soll-Anforderung; Kursstart-Definition offen (Lastenheft §6.2); frühestens 0.4.x | – |
| L-11 | Relative Kriterien (Max/Min, Top-N, Perzentile) | **nicht umgesetzt** | Kann, zweite Ausbaustufe; braucht voraggregierte Kurswerte und eine Entscheidung zur Referenzpopulation | – |
| L-12 | Restrict-access-Oberfläche, kombinierbar mit Core | umgesetzt | UND/ODER/NICHT von Moodle, auch mit Core-Bedingungen | `access_test::test_combinations`, `test_combined_with_core_conditions`, Behat |
| L-13 | DE/EN, barrierearm | teilweise | DE und EN vollständig; Felder mit Label; keine eigene Barrierefreiheitsprüfung (axe) | `lang/`, Behat |
| L-14 | Keine unnötige dauerhafte Kopie von Nutzerdaten | umgesetzt | keine Tabellen, nur Request-Cache | `privacy_test`, [Datenschutz](Datenschutz.md) |

## Qualitätsanforderungen

| Anforderung | Status | Nachweis |
|---|---|---|
| Fail-closed bei fehlenden oder unklaren Daten, auch unter NICHT | umgesetzt | `access_test::test_negation_does_not_release_missing_data`, `test_invalid_rules_do_not_release` |
| Keine Logscans, keine Rangberechnung pro Aktivität | umgesetzt | `performance_test` (Lesezugriffe konstant bei 10/100/500 Aktivitäten), [Testbericht](Testbericht-0.3.0.md) |
| Serverseitige Auswertung, Konfiguration nach Capabilities | umgesetzt | `permissions_test`, [Berechtigungen](Berechtigungen.md) |
| Moodle 4.5–5.3, PostgreSQL und MariaDB | teilweise | PostgreSQL: 4.5–5.3; MariaDB 10.11: 4.5–5.2; MariaDB 11.4 für 5.3 hier nicht vorhanden, siehe [Testbericht](Testbericht-0.3.0.md) |
| Privacy-API-Beurteilung | umgesetzt (technisch) | [Datenschutz](Datenschutz.md); rechtliche Bewertung offen |
| Coding Standards, automatisierte Tests | umgesetzt | phpcs (moodle-cs) 0 Fehler/0 Warnungen; PHPUnit, Behat, JS-Test |
| Zeit nur über Moodle-Clock-API | umgesetzt | `clock_usage_test` (statisch, mit Negativproben), `clock_source_test` |

## Festgelegte Semantik (Entscheidungen)

- **Mehrfacheinschreibung:** mindestens eine aktive Einschreibung erfüllt das Kriterium. Ohne aktive
  Einschreibung fehlt der Wert; „ist keine von“ ist dann nicht erfüllt.
- **Zählmenge Abschluss:** Aktivitäten mit Abschlussverfolgung, die sichtbar geschaltet sind (auch „verfügbar,
  aber nicht auf der Kursseite“), nicht in verborgenen Abschnitten, nicht im Löschvorgang. Zugriffsbeschränkungen
  der Aktivitäten werden nicht geprüft (sonst Rekursion); eine beschränkte Aktivität zählt sich selbst mit. Die
  Rolle der Person spielt keine Rolle. Im Editor als Hinweis erläutert.
- **Kursnote:** wie Core (`availability_grade`) inklusive Skalen, Überschreibungen, verborgener und gesperrter
  Noten. Abweichungen, beide fail-closed: (1) Kursgesamtbewertung wartet auf Neuberechnung → nicht entscheidbar
  (Core nutzt den alten Wert); (2) unter NICHT gibt eine fehlende Note nicht frei (Core gibt frei).
- **Zwischen:** Grenzen wählbar (beide/untere/obere/keine eingeschlossen), Standard „beide“.

## Nicht enthalten (Abgrenzung, Lastenheft §5)

Dashboards, Ranglisten, automatisches Einschreiben, Benachrichtigungen, prädiktive Analyse, Live-Tracking,
Core-Änderungen.
