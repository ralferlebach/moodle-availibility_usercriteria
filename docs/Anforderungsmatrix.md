# Anforderungsmatrix – availability_usercriteria (AUC) 0.4.0

**Stand:** 28.10.2026. Bezug: [Lastenheft](Lastenheft.md), [Pflichtenheft](Pflichtenheft.md). Status:
**umgesetzt** = implementiert und durch die genannten Tests belegt; **teilweise** = mit genannter Einschränkung;
**nicht umgesetzt** = bewusst nicht enthalten. Nachweise: [Testbericht 0.4.0](Testbericht-0.4.0.md).

## Lastenheft – fachliche Anforderungen

| ID | Anforderung | Status | Umsetzung / Einschränkung | Nachweis |
|---|---|---|---|---|
| L-01 | Einzelner Nutzer: ID, Nutzername, Vor-/Nachname | umgesetzt | ID numerisch, Namen als Text | `condition_test::test_identity_and_profile`, `privacy_test` |
| L-02 | Standard- und benutzerdefinierte Profilfelder, feldtypgerecht | umgesetzt | Text, Menü, Checkbox, Datum; Textfelder auf ausdrückliche Wahl auch numerisch | `profile_test`, `condition_test::test_custom_profile_fields` |
| L-03 | Kurs- und Systemrolle | umgesetzt | direkt im Kurs, im Kurs wirksam, Systemrolle | `condition_test::test_roles`, `access_test::test_roles` |
| L-04 | Einschreibedatum vor/nach/zwischen | umgesetzt | Gültigkeitsbeginn, sonst Anlagezeitpunkt; nur aktive Einschreibungen | `enrolment_test`, `clock_source_test` |
| L-05 | Einschreibemethode, mehrere Einschreibungen | umgesetzt | mindestens eine aktive Einschreibung | `enrolment_test` |
| L-06 | Letzter Site-Login und letzte Kursaktivität getrennt | umgesetzt | Kursaktivität = letzter Kurszugriff aus `user_lastaccess` (vom Auftraggeber bestätigt) | `condition_test::test_login_and_course_access`, `clock_source_test` |
| L-07 | Anzahl abgeschlossener Aktivitäten | umgesetzt | zwei Zähler; Zählmenge im Editor erläutert | `completion_test` |
| L-08 | Kursnote mit Vergleichen, fehlende Noten | umgesetzt | wie Core inkl. Skalen; zwei bewusste Abweichungen (fail-closed) | `grade_test`, `grade_parity_test`, Behat |
| L-09 | Nutzungsdauer im Kurs, Schätzmethodik kenntlich | **umgesetzt (0.4.0)** | Schätzung aus dem Standard-Log: Ereignisse ≤ 30 min auseinander bilden eine Sitzung, die Zeit dazwischen zählt (Untergrenze). Geplante Aufgabe alle 10 min, inkrementell; Methode im Editor erläutert | `usage_test` |
| L-10 | Dauer vom Kursstart bis Kursabschluss | **umgesetzt (0.4.0)** | Start = erster im Standard-Log nachweisbarer Kurszugriff (Entscheidung Auftraggeber), Ende = Kursabschluss; ohne Abschluss: Wert fehlt | `usage_test::test_completion_duration*` |
| L-11 | Relative Kriterien (Max/Min, beste/schlechteste N, Perzentile) | **umgesetzt (0.4.0)** | Für Kursbewertung, abgeschlossene Aktivitäten, Nutzungsdauer. Vergleich mit Teilnehmenden (aktive Einschreibung + Teilnehmerrolle laut Plugin-Einstellung, Standard `student`); Werte stündlich voraggregiert ohne Personenbezug; Gleichstand = bessere Position | `relative_test`, Behat |
| L-12 | Restrict-access-Oberfläche, kombinierbar mit Core | umgesetzt | UND/ODER/NICHT von Moodle | `access_test`, Behat |
| L-13 | DE/EN, barrierearm | umgesetzt | DE/EN vollständig (126 Strings); Axe-Prüfung des Editors (WCAG 2.0–2.2 A/AA) | Behat `@accessibility` |
| L-14 | Keine unnötige dauerhafte Kopie von Nutzerdaten | umgesetzt | Gespeichert nur aggregierte Nutzungsdauer und erster Zugriff je Person/Kurs (für L-09/L-10 nötig, Privacy-Export/-Löschung); Vergleichswerte ohne Personenbezug | `privacy_test`, [Datenschutz](Datenschutz.md) |

## Lastenheft – Qualität, Abnahme, offene Punkte §6

| Anforderung | Status | Nachweis |
|---|---|---|
| Fail-closed bei fehlenden/unklaren Daten, auch unter NICHT | umgesetzt | `access_test`, `relative_test`, `usage_test` |
| Keine Logscans, keine Rangberechnung pro Aktivität und Nutzer | umgesetzt | Log und Ränge nur in geplanten Aufgaben; `performance_test`: konstante Zugriffe bei 10/100/500 Aktivitäten |
| Sicherheit: serverseitig, Capabilities | umgesetzt | `permissions_test`, [Berechtigungen](Berechtigungen.md) |
| Kompatibilität 4.5–5.3, MariaDB und PostgreSQL | umgesetzt (lokal) | 10 Kombinationen grün; GitHub Actions siehe [Testbericht](Testbericht-0.4.0.md) |
| Datenschutz, Privacy API | umgesetzt (technisch) | echter Provider; rechtliche Bewertung offen |
| Abnahme: Upgrade-, Berechtigungs-, Performanceprüfung | umgesetzt | Upgrade 0.3.0 → 0.4.0, `permissions_test`, Lasttest parallel |
| §6.1 Zählbasis Abschluss | entschieden | sichtbare, verfolgte Aktivitäten ([Blueprint](Technisches_Blueprint.md)) |
| §6.2 Kursstart | entschieden | erster im Log nachweisbarer Kurszugriff |
| §6.3 Maßgebliche Bewertung | entschieden | normierte Kursgesamtbewertung |
| §6.4 Referenzpopulation, Gleichstände | entschieden | Teilnehmende per Plugin-Einstellung (Standard `student`), Gleichstand = bessere Position |
| §6.5 Login und Kurszugriff getrennt | entschieden | zwei Merkmale |

## Pflichtenheft – Punkte über das Lastenheft hinaus

| Punkt | Status | Nachweis |
|---|---|---|
| §2 Editor erläutert Bezug, Notenmaßstab, Qualität geschätzter Metriken | umgesetzt | Hinweistexte je Merkmal und für relative Operatoren; Behat |
| §2 Fehlerhafte Konfiguration beim Speichern abgefangen | umgesetzt | Editor-Validierung (`fillErrors`), Server-Validierung; `condition_test::test_invalid_structure`, `relative_test::test_structure_and_description` |
| §3 Zwischen mit inklusiven/exklusiven Grenzen | umgesetzt | `comparator_test::test_between_bounds` |
| §3 Relative Operatoren nur mit Referenzpopulation und Stichtag | umgesetzt | Population per Einstellung, Stand der Berechnung höchstens 3 h alt, sonst nicht entscheidbar |
| §5.5 Nicht auswertbar mit administrativ diagnostizierbarem Grund, ohne Personendaten | **umgesetzt (0.4.0)** | Lehrende sehen in der Beschreibung „Hier nicht auswertbar: …“ (Grund bezieht sich auf Seite/Kurs, nie auf Personen) | `usage_test::test_completion_duration_needs_criteria`, `relative_test::test_population` |
| §6 Keine Offenlegung über Editor, Fehlermeldung, API | umgesetzt | `permissions_test`, `privacy_test` |
| §7 Kurzlebiger Cache mit definierter Invalidierung | umgesetzt | Request-Cache + Ereignis-Observer; `cache_test` |
| §7 Lasttests mit vielen Aktivitäten, Teilnehmenden, parallelen Aufrufen | **umgesetzt (0.4.0)** | `performance_test`; paralleler Lasttest 30 Nutzende × 10 Aufrufe ([Testbericht](Testbericht-0.4.0.md)) |
| §8 Integration: suspendierte Einschreibung, vererbte Rollen, Notenüberschreibung, Kursabschluss | umgesetzt | `enrolment_test`, `access_test::test_roles`, `grade_parity_test`, `usage_test` |
| §8 Behat: sichtbare/verborgene Aktivität, Zugriff als betroffene Person | **umgesetzt (0.4.0)** | 9 Szenarien, u. a. „hidden entirely while not met“ |
| §8 Sicherheit: manipulierte Konfiguration, unberechtigter Zugriff, SQL-Escaping | umgesetzt | `access_test::test_invalid_rules_do_not_release`, `test_direct_access_is_refused`, `robustness_test::test_special_characters_are_literal` |
| Sicherung/Wiederherstellung (Abnahme „speichert und lädt verlustfrei“) | **umgesetzt (0.4.0)** | Rollen- und Nutzer-IDs werden beim Restore umgeschrieben; `restore_test` |
| §10 Definition of Done | weitgehend | offen: GitHub-Actions-Lauf der finalen Fassung, rechtliche Datenschutzbewertung |

## Bewusst nicht enthalten (Lastenheft §5)

Dashboards, Ranglisten für Lehrende, automatisches Einschreiben, Benachrichtigungen, prädiktive Analyse,
Live-Tracking, Core-Änderungen, Pflicht-Abhängigkeit von `block_dedication`.
