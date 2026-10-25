# Arbeitsplanung – availability_usercriteria (AUC) bis zum MVP

**Stand:** 2026-10-25 (Session 001) · Grundlage: `Lastenheft.md`, `Pflichtenheft.md`, `Technisches_Blueprint.md`

## 1. Entscheidungen für diese Planung

| Thema | Entscheidung | Quelle |
|---|---|---|
| MVP-Umfang | Gesamtes MVP laut Pflichtenheft §9 (L-01 bis L-08, L-12 bis L-14) | Auftraggeber, 24.10.2026 |
| Zielversionen | Moodle **4.5 LTS und 5.3** zuerst; 5.0 bis 5.2 über die CI-Matrix | Auftraggeber |
| Fachliche Defaults | Lastenheft-Vorschläge übernommen (siehe §5) | Auftraggeber |
| Ablage | Nur lokal als Dateien, kein Push | Auftraggeber |
| Nicht im MVP | L-09 Nutzungsdauer, L-10 Dauer bis Abschluss, L-11 relative Kriterien (Phase 2) | Pflichtenheft §9 |

## 2. Stand der Umsetzung (Session 001)

Eine erste lauffähige Version von `availability_usercriteria` liegt vor. Nachweis jeweils real ausgeführt:

- **PHPUnit:** 65 Tests / 234 Assertions grün auf **Moodle 4.5 (PostgreSQL 16)** und **Moodle 5.3 (PostgreSQL 17)**.
- **Code-Style:** `phpcs --standard=moodle` ohne Befund, standalone und im Moodle-4.5-Baum.
- **Editor im Browser (Moodle 4.5, Chromium):** Plugin erscheint im Restrict-access-Dialog; Speichern erzeugt korrektes JSON; ungültiger Wert blockiert das Speichern; Neuladen stellt die Werte wieder her; Teilnehmende sehen „Not available unless: Course grade (%) is at least 50“ ohne eigene Werte.

| ID | Anforderung | Status | Nachweis / Anmerkung |
|---|---|---|---|
| L-01 | Identität (ID, Nutzername, Vor-/Nachname) | umgesetzt | Unit; LIKE als „enthält / beginnt / endet“, kein SQL |
| L-02 | Profilfelder | umgesetzt, Teilmenge | Standardfelder: ID-Nummer, Institution, Abteilung, Ort, Land, Sprache, E-Mail, Telefon, Mobiltelefon, Adresse, zweiter Vorname, alternativer Name. Benutzerdefiniert: Text, Menü, Checkbox, Datum. Moodle kennt keinen numerischen Profilfeldtyp. Kontaktdaten werden in Meldungen nie ausgeschrieben |
| L-03 | Rolle | umgesetzt | direkt / wirksam (geerbt) im Kurs, Systemrolle; Unit mit geerbter Rolle |
| L-04 | Einschreibedatum | umgesetzt | vor/nach/zwischen/in den letzten X Tagen; Beginn der Gültigkeit, ersatzweise `timecreated` |
| L-05 | Einschreibemethode | umgesetzt | mindestens eine **aktive** Einschreibung; Unit mit Suspendierung |
| L-06 | Site-Login / Kurszugriff getrennt | umgesetzt, **Abweichung** | Kurszugriff aus `user_lastaccess`, nicht aus Logs (kein Logscan, unabhängig von Retention) |
| L-07 | Anzahl abgeschlossener Aktivitäten | umgesetzt | sichtbar, mit Abschlussverfolgung, abgeschlossen oder bestanden; nicht „nicht bestanden“ |
| L-08 | Kursnote | umgesetzt | 0–100 %, ungraded = fehlend (nicht 0) |
| L-12 | Restrict-access-UI, kombinierbar | umgesetzt (4.5 im Browser) | 5.3: nur PHPUnit |
| L-13 | DE/EN, barrierearm | teilweise | Strings DE/EN vollständig; Labels vorhanden; **kein Axe-/Screenreader-Test** |
| L-14 | Keine Datenkopie | umgesetzt | keine Tabellen; nur Request-Cache; Privacy-Provider = null_provider |
| L-09/10/11 | Nutzungsdauer, Dauer bis Abschluss, relative Kriterien | offen | Phase 2 |

## 3. Abnahme-Gates

| Gate | Inhalt | Stand |
|---|---|---|
| G1 | Editor speichert/lädt alle zulässigen Bedingungen | **teilweise**: 4.5 im Browser für Note, Einschreibemethode, Einschreibedatum; Text-, Rollen-, Profil- und Tage-Operatoren sowie 5.3 nicht im Browser geprüft |
| G2 | Alle Muss-Merkmale inkl. NULL-/Grenzfälle korrekt | **erfüllt** (Unit/Integration, fail-closed auch unter NOT) |
| G3 | Kurs-/Systemkontext, Mehrfacheinschreibung | **teilweise**: Suspendierung, geerbte Rollen getestet; mehrere gleichzeitige Einschreibemethoden und Notenüberschreibung noch nicht |
| G4 | Keine N+1 / ungebremste Logscans | **teilweise**: kein Logzugriff; Test „50 Auswertungen = 1 Lesevorgang“; **kein Lasttest** mit vielen Teilnehmenden/Aktivitäten |
| G5 | Tests auf freigegebenen Versionen/DBs | **teilweise**: 4.5/PG16 und 5.3/PG17 real; MariaDB und 5.0–5.2 offen |

## 4. Restarbeit bis „MVP freigabefähig“

Reihenfolge nach Risiko. Aufwände in Personentagen (PT), Schätzungen, keine Zusagen.

| # | Arbeitspaket | Gate | PT | Hinweis |
|---|---|---|---|---|
| 1 | Verbleibende Fachfrage klären (§5, Punkt 1: Regrade) | G2 | 0,25 | |
| 2 | Weitere Integrationstests: mehrere gleichzeitige Einschreibemethoden, Notenüberschreibung, Kursabschluss, Zeitzonen-Grenzen, manipulierte Konfiguration | G2, G3 | 1 | PHPUnit |
| 3 | Editor-Browsertests als Playwright-Suite (alle Operatortypen, Rollen/Profil, Fehlerfälle), Axe-Prüfung, auch auf 5.3 | G1, L-13 | 1–1,5 | Vorlage: der Lauf aus Session 001 |
| 4 | Lasttest: ≥ 500 Teilnehmende × 50 eingeschränkte Aktivitäten, Kursseite und `get_fast_modinfo`; Verhalten des Core-Availability-Cachings bei Noten-/Completion-Änderung klären | G4 | 1 | siehe Risiko R3 |
| 5 | Backup/Restore: Rollen- und Profilfeld-IDs beim Wiederherstellen (`update_after_restore`), Test | G3 | 0,5–1 | Rollen werden als IDs gespeichert |
| 6 | MariaDB-Lauf lokal (4.5, 10.11) und CI-Matrix 4.5/5.0/5.1/5.2/5.3 × PG/MariaDB anstoßen | G5 | 0,5 | Matrix liegt in `.github/workflows` bereit, **noch nie gelaufen** |
| 7 | Behat-Feature (liegt vor, **nie ausgeführt**) in der CI zum Laufen bringen | G1 | 0,5–1 | Browser-Treiber nötig |
| 8 | Echter `grunt yui`-Build in der CI gegenprüfen, ESLint | Qualität | 0,25 | lokal scheitert Shifter-Download (403) |
| 9 | Privacy-Provider-Test, README, CHANGELOG, `MATURITY_BETA` setzen, Release-Gates der CI | DoD | 0,5 | |

**Summe Restarbeit:** etwa 5,5–7,5 PT. Davon sind 2–4 (Testtiefe) und 6–7 (Versionsmatrix, CI) die
eigentlichen Freigabegates; 5 ist eine Funktionslücke.

### Vorschlag zur Reihenfolge

1. **Tag 1:** #1 Entscheidungen, #2 Integrationstests, #8 Build-Prüfung.
2. **Tag 2–3:** #3 Playwright-Suite und #6 MariaDB/Matrix parallel.
3. **Tag 4:** #4 Lasttest, #5 Backup/Restore.
4. **Tag 5:** #7 Behat, #9 Release-Hygiene → Gate-Review G1–G5.

Phase 2 (L-09, L-10, L-11) erst danach (Pflichtenheft: zusätzlich 7–15 PT).

## 5. Fachliche Entscheidungen

**Als Default übernommen (Auftraggeber, 24.10.2026):**
- Completion zählt alle abschließbaren, sichtbaren Aktivitäten mit Abschlussverfolgung; „bestanden“ zählt, „nicht bestanden“ nicht.
- Kursnote = normierte Kursgesamtbewertung 0–100 %; unbewertet = fehlend.
- Mehrfacheinschreibung: mindestens eine aktive Einschreibung erfüllt das Kriterium.
- Fehlende oder nicht ermittelbare Daten: nicht erfüllt, auch unter NOT. Gezielt abfragbar über „ist nicht vorhanden“.
- Site-Login und Kurszugriff sind getrennte Merkmale.

**Entschieden am 2026-10-25:**
- Weitere Standardprofilfelder (E-Mail, Telefon, Mobiltelefon, Adresse, zweiter Vorname, alternativer Name) sind wählbar. Sie werden wie Identitätsmerkmale behandelt: Wert und Merkmal erscheinen nie in der Sperrmeldung.
- Identitätsmerkmale (ID, Nutzername, Namen) bleiben für alle wählbar, die Voraussetzungen bearbeiten dürfen (in der Praxis vor allem Administratoren und Lehrende mit Bearbeitungsrecht). Keine zusätzliche Capability.
- Letzter Kurszugriff kommt aus `user_lastaccess` (nicht aus den Logs). R1 ist damit bestätigt.

**Noch offen:**
1. Soll eine Kursnote mit `needsupdate` (Regrade ausstehend) weiter als nicht entscheidbar gelten? Das ist fail-closed, kann aber Teilnehmende bis zum nächsten Regrade sperren.
2. Kursstart (L-10), Berechnung der Nutzungsdauer (L-09), Referenzpopulation und Gleichstände (L-11): Phase 2.

## 6. Risiken und bekannte Abweichungen

| # | Risiko / Abweichung | Maßnahme |
|---|---|---|
| R1 | Kursaktivität kommt aus `user_lastaccess` statt aus Logs (Pflichtenheft P-07). Der Wert wird nur bei Kurszugriffen aktualisiert und ist gröber als Logs | bestätigt am 2026-10-25; im Editor-Hinweis ausgewiesen |
| R2 | Datumswerte im Editor werden mit dem Zeitzonen-Offset von „jetzt“ umgerechnet; über Sommer-/Winterzeit hinweg kann ein Wert um bis zu 1 Stunde abweichen | in #3 prüfen; ggf. serverseitige Umrechnung |
| R3 | Core-Verhalten beim Caching von Verfügbarkeit nach Noten-/Completion-Änderung wurde nicht untersucht. AUC selbst cached nur innerhalb eines Seitenaufrufs | #4 |
| R4 | Rollen werden als IDs gespeichert; nach Restore auf anderer Site können IDs abweichen | #5 |
| R5 | Editor in 5.3 nur über PHPUnit/API-Vergleich geprüft, nicht im Browser | #3 |
| R6 | Die Sperrmeldung zeigt die konfigurierte Schwelle (z. B. 50 %). Identitäts- und Kontaktmerkmale werden bewusst nicht ausgeschrieben | Verhalten ist getestet |

## Stand 0.2.0 (2026-10-25)

Erledigt: alle P0-Punkte (Cache/Zeit, Enrolment-Semantik, Grenzen, exakte Ganzzahlvergleiche, Grade-Parität,
Completion-Zählung), Clock-API ausnahmslos (durch Test erzwungen), DST-sicherer Editor, numerische
Profilfelder, Datenschutztext, CI-Korrektur, 181 PHPUnit-Tests auf 4.5 und 5.3 grün.

Entscheidungen, die Freigabe brauchen:
1. Kein aktives Enrolment = Wert fehlt; "ist keine von" ist dann nicht erfüllt (nur "ist nicht verfügbar" erfüllt).
2. "zwischen" mit wählbaren Grenzen; Standard "beide" (kompatibel zu 0.1.0).
3. Abschluss: zwei Zähler (abgeschlossen = bestanden/abgeschlossen; beendet = zusätzlich nicht bestanden).
4. Letzter Kurszugriff aus user_lastaccess (bestätigt).
5. L-09/L-10/L-11 (Nutzungsdauer, Dauer bis Abschluss, relative Kriterien) auf 0.3.x verschoben.

Offen / nicht verifiziert: Behat- und Playwright-E2E (nicht lauffähig in dieser Umgebung, daher bewusst nicht
als "getestet" ausgewiesen), GitHub-Actions-Lauf, MariaDB auf 5.3, Moodle 5.0-5.2, gemessene Performance
(nur Test, dass Abfragen nicht mit der Aktivitätenzahl wachsen), rechtliche Datenschutzprüfung.

## Stand 0.3.0 (2026-10-25)

Grundlage: Re-Audit `AUC_ReAudit_0.2.0`. Alle Befunde gegengeprüft (siehe `docs/sessions/session-002.md`) und
bearbeitet; Nachweise im [Testbericht](Testbericht-0.3.0.md), Stand je Anforderung in der
[Anforderungsmatrix](Anforderungsmatrix.md).

| Befund | Ergebnis 0.3.0 |
|---|---|
| 020-01 Berechtigungen | Entscheidung dokumentiert (keine eigene Capability, wie `availability_profile`), Matrix getestet, Editor gibt keine Personendaten preis → [Berechtigungen](Berechtigungen.md) |
| 020-02 Gradebook | Vergleichstests gegen Core; Skalen angeglichen; zwei Abweichungen bewusst (fail-closed) |
| 020-03 Completion | Zählmenge festgelegt, im Editor erläutert, Tests für verborgen, Abschnitt verborgen, Stealth, gesperrt, gelöscht, ohne Tracking, Rollen, wechselseitige Abhängigkeit |
| 020-04 Cache | Ereignis-Observer; Gültigkeitsmodell je Merkmal im Blueprint §11; Tests für Änderungen innerhalb einer Anfrage |
| 020-05 Zeitquelle | Gate um JS und dynamische Aufrufe erweitert, Negativproben, Beweis der Uhrquelle mit eingefrorener Uhr 2001; eigener CI-Schritt |
| 020-06 Datenschutz | Export/Löschung geprüft, Fehlermeldungen ohne Werte → [Datenschutz](Datenschutz.md) |
| 020-07 Release-Artefakt | `docs/` wird ausgeliefert, Linkprüfung im CI |
| 020-08 Testmatrix | 4.5–5.3 × PostgreSQL/MariaDB lokal grün, Behat 4.5/5.3 grün; CI korrigiert (DB-Versionen für 5.3, Template-Job entfernt, Warnungen 0) |
| 020-09 Funktionsumfang | L-09/L-10/L-11 als „nicht umgesetzt“ markiert (README, Matrix) |

Scope-Entscheidung (Vorschlag, bitte bestätigen): 0.3.x bleibt Stabilisierung und Freigabe des MVP.
L-09 (Nutzungsdauer, nur mit externem Datenlieferanten) und L-10 (Dauer bis Abschluss, Kursstart-Definition
offen) frühestens 0.4.x; L-11 (relative Kriterien) frühestens 0.5.x mit voraggregierten Werten. Keine eigene
Statistik-Infrastruktur im Plugin.

Entscheidungen, die Freigabe brauchen:
1. Kursbewertung bei ausstehender Neuberechnung: nicht entscheidbar (gesperrt) statt Core-Verhalten (alter Wert).
2. Kursbewertung als Skala jetzt wie Core in Prozent (vorher nicht entscheidbar).
3. Keine eigene Capability für Identitätsmerkmale (Restrisiko in [Berechtigungen](Berechtigungen.md)).
4. Zählmenge der Abschlusszähler wie oben (Zugriffsbeschränkungen der gezählten Aktivitäten werden nicht geprüft).
5. Scope wie oben.

Offen: GitHub-Actions-Lauf (nicht gepusht), PHPDoc-Check und `validate` nur in der CI, Behat auf 5.0–5.2 und
MariaDB, Last mit parallelen Zugriffen, Backup/Restore einer Regel, rechtliche Datenschutzbewertung.
