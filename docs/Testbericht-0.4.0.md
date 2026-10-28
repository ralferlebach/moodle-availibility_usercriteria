# Testbericht – availability_usercriteria 0.4.0 (2026102800)

**Ausgeführt:** 28./29.10.2026 in der Entwicklungsumgebung (Container, Linux x86_64, 2 CPU-Kerne), PHP 8.3.6,
Node 22. Eingetragen ist nur, was tatsächlich gelaufen ist; reproduzierbar mit `tools/run_matrix.sh`, Behat und
`tools/load/` ([environment-setup.md](environment-setup.md) §8a/§8b). **GitHub Actions ist für diese Fassung nicht
gelaufen** (nicht gepusht).

## PHPUnit (230 Tests, 1147 Assertions je Kombination)

| Moodle | Datenbank | PHPUnit | Fehlschläge / Fehler / Übersprungen | Dauer | Ergebnis |
|---|---|---|---|---|---|
| 4.5.15+ (Build 20261021) | PostgreSQL 16.15 | 9.6.34 | 0 / 0 / 0 | 46 s | bestanden |
| 5.0.11 (Build 20261005) | PostgreSQL 16.15 | 11.5.55 | 0 / 0 / 0 | 52 s | bestanden |
| 5.1.8+ (Build 20261021) | PostgreSQL 16.15 | 11.5.55 | 0 / 0 / 0 | 55 s | bestanden |
| 5.2.4+ (Build 20261021) | PostgreSQL 16.15 | 11.5.55 | 0 / 0 / 0 | 58 s | bestanden |
| 5.3+ (Build 20261021) | PostgreSQL 17.11 | 11.5.56 | 0 / 0 / 0 | 59 s | bestanden |
| 4.5.15+ (Build 20261021) | MariaDB 10.11.14 | 9.6.34 | 0 / 0 / 0 | 62 s | bestanden |
| 5.0.11 (Build 20261005) | MariaDB 10.11.14 | 11.5.55 | 0 / 0 / 0 | 51 s | bestanden |
| 5.1.8+ (Build 20261021) | MariaDB 10.11.14 | 11.5.55 | 0 / 0 / 0 | 56 s | bestanden |
| 5.2.4+ (Build 20261021) | MariaDB 10.11.14 | 11.5.55 | 0 / 0 / 0 | 64 s | bestanden |
| 5.3+ (Build 20261021) | MariaDB 11.4.13 | 11.5.56 | 0 / 0 / 0 | 58 s | bestanden |

Ein vorheriger Lauf derselben Fassung scheiterte auf MariaDB an einem Testfehler (`usage_test::test_incremental`):
MariaDB schreibt in PHPUnit Log-Einträge sofort, PostgreSQL erst beim Commit; der Test zählte Einträge aus dem
Testaufbau mit. Der Test zählt jetzt ab einem leeren Stand; die Tabelle oben ist der Lauf danach, ohne
Codeänderung dazwischen.

Hinweise unter PHPUnit 11: 23 „PHPUnit Deprecations“ (Metadaten in Doc-Kommentaren, nötig für PHPUnit 9.6 auf
Moodle 4.5). Sonst keine Warnungen oder Hinweise. Nicht geprüft: MySQL, MSSQL, Aurora, andere PHP-Versionen.

## Behat (Chrome 156 headless, ChromeDriver 156, Apache 2.4 + mod_php)

| Moodle | Datenbank | Szenarien | Schritte | Ergebnis |
|---|---|---|---|---|
| 4.5.15+ | PostgreSQL 16.15 | 9 | 114 | bestanden |
| 5.3+ | PostgreSQL 17.11 | 9 | 114 | bestanden |

Neu in 0.4.0: Aktivität bei nicht erfüllter Regel ganz verborgen; relative Kriterien und Nutzungsdauer im
Editor (Hinweistexte, Speichern, Wiederöffnen); Plugin-Einstellung Teilnehmerrollen (Standard „Student“);
Notenbedingung in einem Kurs mit Bewertungen (Regression, siehe unten). Weiterhin: Speichern/Wiederöffnen mit
Axe-Prüfung, echte Zugriffssperre, Hinweistext, Identitätsregel ohne Wert, Sommerzeit.
Nicht ausgeführt: Behat auf 5.0–5.2 und auf MariaDB.

## Performance (`tests/performance_test.php`)

Kursseite mit 10/100/500 Aktivitäten, jede mit 10 Bedingungen (eine je Datenquelle, dazu eine relative), für eine
Person aufgebaut. Lesezugriffe ohne Beschränkung / mit Beschränkung (+ durch das Plugin).

| Moodle | Datenbank | 10 Aktivitäten | 100 Aktivitäten | 500 Aktivitäten |
|---|---|---|---|---|
| 4.5 | PostgreSQL | 16 / 25 (+9), 0,005 s / 0,010 s | 102 / 114 (+12), 0,014 s / 0,034 s | 502 / 514 (+12), 0,073 s / 0,239 s |
| 5.0 | PostgreSQL | 16 / 25 (+9), 0,005 s / 0,015 s | 102 / 114 (+12), 0,017 s / 0,039 s | 502 / 514 (+12), 0,089 s / 0,209 s |
| 5.1 | PostgreSQL | 16 / 25 (+9), 0,006 s / 0,016 s | 102 / 114 (+12), 0,021 s / 0,086 s | 502 / 514 (+12), 0,111 s / 0,217 s |
| 5.2 | PostgreSQL | 16 / 25 (+9), 0,006 s / 0,013 s | 102 / 114 (+12), 0,019 s / 0,052 s | 502 / 514 (+12), 0,108 s / 0,245 s |
| 5.3 | PostgreSQL | 16 / 25 (+9), 0,006 s / 0,011 s | 102 / 114 (+12), 0,025 s / 0,060 s | 502 / 514 (+12), 0,082 s / 0,247 s |
| 4.5 | MariaDB | 12 / 23 (+11), 0,015 s / 0,015 s | 100 / 112 (+12), 0,014 s / 0,031 s | 500 / 512 (+12), 0,086 s / 0,196 s |
| 5.0 | MariaDB | 12 / 23 (+11), 0,004 s / 0,009 s | 100 / 112 (+12), 0,016 s / 0,033 s | 500 / 512 (+12), 0,077 s / 0,158 s |
| 5.1 | MariaDB | 12 / 23 (+11), 0,007 s / 0,013 s | 100 / 112 (+12), 0,021 s / 0,056 s | 500 / 512 (+12), 0,091 s / 0,295 s |
| 5.2 | MariaDB | 12 / 23 (+11), 0,005 s / 0,016 s | 100 / 112 (+12), 0,020 s / 0,080 s | 500 / 512 (+12), 0,113 s / 0,197 s |
| 5.3 | MariaDB 11.4 | 12 / 23 (+11), 0,006 s / 0,013 s | 100 / 112 (+12), 0,018 s / 0,036 s | 500 / 512 (+12), 0,098 s / 0,341 s |

Ergebnis: AUC fügt **höchstens 12 Lesezugriffe pro Seite** hinzu, unabhängig von der Zahl der Aktivitäten
(5000 Bedingungen bei 500 Aktivitäten). Zeiten sind Einzelmessungen in einem geteilten Container.

## Paralleler Lasttest (`tools/load/`, Pflichtenheft §7)

Moodle 4.5 (Entwicklungsseite, PostgreSQL 16, Apache mod_php, 2 CPU-Kerne), Kurs mit 100 Seiten und 30
Teilnehmenden mit Noten. „Mit Regeln“: jede Seite mit 9 Bedingungen (darunter Kursbewertung, Nutzungsdauer und
eine relative Bedingung) = 900 Bedingungen pro Seitenaufruf. 30 angemeldete Personen rufen gleichzeitig je 10-mal
die Kursseite ab (300 Aufrufe), zwei Runden, nach einem Aufwärmaufruf.

| Kurs | Aufrufe | Fehler | Median | 95. Perzentil | Maximum |
|---|---|---|---|---|---|
| ohne Regeln, Runde 1 | 300 | 0 | 2,82 s | 3,45 s | 4,92 s |
| mit Regeln, Runde 1 | 300 | 0 | 3,22 s | 3,67 s | 3,93 s |
| ohne Regeln, Runde 2 | 300 | 0 | 2,87 s | 3,37 s | 3,74 s |
| mit Regeln, Runde 2 | 300 | 0 | 3,21 s | 3,62 s | 4,10 s |

Ergebnis: keine Fehler, rund +12 % im Median bei 30 gleichzeitigen Personen auf 2 Kernen (die Antwortzeiten
selbst sind durch die Container-Hardware bestimmt). Einzeln gemessen: 900 Bedingungen in 0,038 s, 14
Lesezugriffe (ohne Regeln: 0,006 s, 4 Zugriffe).

Der Lasttest fand zwei Fehler, die vorher kein Test zeigte; beide sind behoben:
1. **HTTP 500 auf der Kursseite** bei Notenbedingungen in Kursen mit Bewertungen (`GRADE_TYPE_VALUE` nicht
   definiert, weil eine Kursseite `gradelib` nicht lädt; seit 0.2.0). Regression: Behat-Szenario „A course grade
   condition in a course with a gradebook does not break the course page“, schlägt mit dem alten Code fehl.
2. **Ein Lesezugriff pro Aktivität** bei relativen Bedingungen (Rollenabfrage am Request-Cache vorbei: 113 statt 14
   Zugriffe). Der Performance-Test enthält jetzt eine relative Bedingung.

## Weitere Gates

| Gate | Ergebnis |
|---|---|
| phpcs moodle-cs (wie CI, `--max-warnings 0`) | 0 Fehler, 0 Warnungen |
| `php -l` aller PHP-Dateien | fehlerfrei |
| Editor-Zeitzonen-Test `node tests/js/form_tz_test.js` | 13 Prüfungen bestanden |
| `python3 tools/build_yui.py --check` | `yui/build` entspricht `yui/src` |
| Zeitquellen-Gate (`clock_usage_test`, `clock_source_test`) | in jeder Matrixzeile bestanden; neue Aufgaben und Leser nutzen `\core\clock` |
| Upgrade 0.3.0 → 0.4.0 auf der 4.5-Entwicklungsseite | erfolgreich; beide Tabellen angelegt, `check_database_schema`: „Database structure is ok“, Einstellung Teilnehmerrollen = Student, 2 geplante Aufgaben registriert, vorhandene Regel unverändert |
| Geplante Aufgaben über Moodle-CLI (`scheduled_task.php --execute`) | `update_usage`: 55 Log-Einträge verarbeitet; `update_distributions`: ausgeführt |
| Moodle PHPDoc-Check, `moodle-plugin-ci validate/savepoints` | **nicht lokal ausgeführt** (laufen in der CI) |
