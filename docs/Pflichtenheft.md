# Pflichtenheft – availability_usercriteria (AUC)

**Stand:** 24.10.2026 · **Status:** Technischer Lösungsvorschlag, nicht implementiert

## 1. Systemgrenze
Ein Moodle-Plugin vom Typ `availability` mit Komponentenname `availability_usercriteria`. Keine eigenständige Web-App, keine Core-Forks und grundsätzlich keine eigene Nutzerdatenhaltung. Der Moodle-Verfügbarkeitsmechanismus übernimmt Kombinationen, Darstellung des Sperrstatus und Aufruf der Auswertung.

## 2. Bedienung
Im Restrict-access-Editor ist „Nutzerkriterien“ wählbar. Eine Bedingung umfasst **Kategorie → Merkmal → Operator → Vergleichswert**, gegebenenfalls Einheit, Zeitfenster und Einschreibestatus. Nur zum Feldtyp passende Operatoren sind auswählbar. Der Editor erläutert den Bezug (Site/Kurs), den Notenmaßstab und die Qualität geschätzter Metriken. Fehlerhafte Konfigurationen werden beim Speichern abgefangen; ungültige gespeicherte Regeln werden beim Auswerten nicht als erfüllt behandelt.

## 3. Daten- und Operatorenmodell
- **Text:** gleich, ungleich, enthält, enthält nicht, beginnt/endet mit; LIKE nur als definierte, sicher escapte Semantik, keine rohe SQL-Eingabe.
- **Zahlen:** =, !=, >, >=, <, <=, zwischen mit explizit inklusiven/exklusiven Grenzen.
- **Zeit:** vor, nach, zwischen, „in den letzten X Tagen“; Zeitstempel in UTC, Darstellung in Nutzerzeitzone.
- **Auswahl:** ist eines von / ist keines von.
- **NULL/fehlend:** eigene Zustände, nicht als Nullwert oder Note 0 interpretieren.
- **Relative Operatoren:** optional, nur mit festgelegter Referenzpopulation und Stichtag.

## 4. Datenquellen und Sollsemantik
| ID | Merkmal | Datenquelle / Auswertung | Sonderfall |
|---|---|---|---|
| P-01 | Nutzeridentität | Moodle User API / User-Daten | ID numerisch, Nutzername als Text |
| P-02 | Profilfelder | Moodle User-Profile-API | Feldtyp, Mehrfachauswahl, fehlender Wert |
| P-03 | Rolle | Moodle Role/Context APIs | Direkte Zuweisung vs. geerbte Rolle explizit unterscheiden |
| P-04 | Einschreibemethode | Moodle Enrolment API | Standard: mindestens eine passende **aktive** Einschreibung |
| P-05 | Einschreibedatum | Nutzer-Einschreibedatensatz / Enrolment API | Standard: Gültigkeitsbeginn, nicht blind `timecreated` |
| P-06 | Site-Login | Moodle User-Daten | „Nie eingeloggt“ separat |
| P-07 | Kursaktivität | letztes relevantes Kursereignis aus verfügbaren Logs | Logretention, fehlende Logs und Zugriffskontext beachten |
| P-08 | Aktivitätsabschlüsse | Completion API | Status „abgeschlossen“, einschließlich bestanden/nicht bestanden nach definierter Zählregel |
| P-09 | Kursnote | Gradebook API, Kursgesamtbewertung | Standard: normiert 0–100 %, fehlende Note separat |
| P-10 | Nutzungsdauer | optional Dedication-Datenprovider | geschätzt, Verfügbarkeit und Aktualität prüfen |
| P-11 | Dauer bis Abschluss | Einschreib-/Startzeit und Kursabschlusszeit | kein Abschluss → fehlender Wert |

## 5. Regelprüfung
1. Aktuellen Nutzer und Kurs-/Modulkontext aus Moodle übernehmen; keine clientseitigen Werte vertrauen.
2. Gespeicherte Regel gegen eine versionsfähige, typisierte Schema-Definition validieren.
3. Feld und Operator auf Zulässigkeit prüfen; benötigte Moodle-Daten mit den jeweiligen APIs abrufen.
4. Wert typisiert vergleichen und ein boolesches Ergebnis an die Moodle-Availability-API zurückgeben.
5. Bei fehlender Quelle, ungültiger Konfiguration oder nicht ermittelbarer Metrik: **nicht erfüllt**, mit administrativ diagnostizierbarem Grund, ohne personenbezogene Daten zu protokollieren.
6. Vorhandene Moodle-Regelgruppen übernehmen UND/ODER/NOT; AUC benötigt dafür keinen eigenen Regelbaum.

## 6. Berechtigungen und Datenschutz
- Bearbeitung ausschließlich mit den vorhandenen Moodle-Rechten zum Bearbeiten von Aktivitäten/Voraussetzungen.
- Serverseitige Prüfung von Kurs- und Systemkontext; keine Offenlegung von fremden Nutzerwerten über Editor, Fehlermeldung oder API.
- Gespeicherte Konfiguration darf keine unnötigen personenbezogenen Klardaten enthalten; gezielte ID-/Namensvergleiche sind entsprechend als sensible Konfiguration zu behandeln.
- Privacy-API prüfen und dokumentieren; bei fehlender eigener personenbezogener Speicherung begründete No-data-Erklärung, andernfalls Export-/Löschunterstützung.
- Keine automatisierten Folgen über das Sperren/Freigeben hinaus.

## 7. Performance
- Keine pro Nutzer/Modul wiederholten Vollscans über Standardlogs.
- Bei häufig abgefragten Kursmetriken nur gezielte, indizierbare Abfragen bzw. vorhandene Core-API-Aggregationen.
- Bei Bedarf kurzlebiger Cache mit definierter Invalidierung; veraltete Ergebnisse dürfen keine unbeabsichtigte Freigabe erzeugen.
- Relative Kriterien nur in Phase 2, mit voraggregierten Kurswerten, definierter Aktualität und Grenzwerten für große Kurse.
- Nachweis durch Lasttests mit vielen Aktivitäten, Teilnehmern und parallelen Seitenaufrufen; N+1-Muster untersuchen.

## 8. Tests und Abnahme
- PHPUnit: Operatorgrenzen, Textvergleich, NULL, Profilfeldtypen, Notengrenzen, Zeitzonen, fehlende Daten.
- Integration: mehrere Einschreibemethoden, suspendierte Einschreibung, vererbte Rollen, Notenüberschreibung, Kursabschluss, Completion-Status.
- Behat: Restrict-access-Editor, Speichern/Wiederöffnen, sichtbare/verborgene Aktivität, Kursansicht, Zugriff als betroffener Nutzer.
- Sicherheit: Capabilities, manipulierte Konfiguration, unberechtigter Zugriff, SQL-Escaping.
- Performance: viele Teilnehmende und Aktivitäten, Logvolumen, Cache-Invaliderung.
- Kompatibilität: reale Tests auf Moodle 4.5, 5.0, 5.1, 5.2, 5.3 soweit freigegeben; PostgreSQL und MariaDB.
- CI grün allein reicht nicht: dokumentierte Ausführung, keine verdeckten Skips, reproduzierbare Testfälle.

## 9. Umsetzungsetappen und Aufwand
**MVP:** Identität, Profilfelder, Rollen, Enrolment, Noten, Completion-Anzahl, letzte Aktivität; Editor und Tests. Grob 9–16 PT bei vorhandenen Core-Daten und begrenztem UI.

**Erweiterung:** Dedication-Zeit, Dauer bis Abschluss, relative Kriterien. Zusätzlich grob 7–15 PT; abhängig von Datengrundlage und Performance. Zahlen sind Schätzungen, keine Abnahmezusagen.

## 10. Definition of Done
Installier- und upgradefähig; Moodle-Coding-Standards; DE/EN-Strings; korrekte Zugriffsentscheidungen und Fehlwertbehandlung; keine unnötigen Datenkopien; dokumentierte Metriksemantik; echte PHPUnit-/Behat-/Performance-Nachweise; keine ungeprüfte Versionsfreigabe.
