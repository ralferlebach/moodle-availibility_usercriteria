# Testbericht – availability_usercriteria 0.3.0 (2026102502)

**Ausgeführt:** 25.10.2026 in der Entwicklungsumgebung (Container, Linux x86_64), PHP 8.3.6, Node 22.23.4.
Eingetragen ist nur, was tatsächlich gelaufen ist. Reproduzierbar mit `tools/run_matrix.sh`, Behat und den
Gates wie in [environment-setup.md](environment-setup.md) §8a beschrieben. **GitHub Actions ist nicht gelaufen**
(das Repository wurde nicht gepusht).

## PHPUnit (208 Tests, 1000 Assertions je Kombination)

| Moodle | Datenbank | PHPUnit | Fehlschläge / Fehler / Übersprungen | Dauer | Ergebnis |
|---|---|---|---|---|---|
| 4.5.15+ (Build 20261021) | PostgreSQL 16.15 | 9.6.34 | 0 / 0 / 0 | 56 s | bestanden |
| 5.0.11 (Build 20261005) | PostgreSQL 16.15 | 11.5.55 | 0 / 0 / 0 | 50 s | bestanden ¹ |
| 5.1.8+ (Build 20261021) | PostgreSQL 16.15 | 11.5.55 | 0 / 0 / 0 | 46 s | bestanden |
| 5.2.4+ (Build 20261021) | PostgreSQL 16.15 | 11.5.55 | 0 / 0 / 0 | 55 s | bestanden |
| 5.3+ (Build 20261021) | PostgreSQL 17.11 | 11.5.56 | 0 / 0 / 0 | 44 s | bestanden |
| 4.5.15+ (Build 20261021) | MariaDB 10.11.14 | 9.6.34 | 0 / 0 / 0 | 51 s | bestanden |
| 5.0.11 (Build 20261005) | MariaDB 10.11.14 | 11.5.55 | 0 / 0 / 0 | 50 s | bestanden |
| 5.1.8+ (Build 20261021) | MariaDB 10.11.14 | 11.5.55 | 0 / 0 / 0 | 54 s | bestanden |
| 5.2.4+ (Build 20261021) | MariaDB 10.11.14 | 11.5.55 | 0 / 0 / 0 | 53 s | bestanden |
| 5.3+ (Build 20261021) | MariaDB 11.4.13 | 11.5.56 | 0 / 0 / 0 | 58 s | bestanden ² |

¹ Erster Lauf scheiterte am Init (halbe Testtabellen aus einem abgebrochenen Vorlauf); nach Neuanlage der
Testdatenbank wiederholt, Code unverändert. ² Separat installiertes MariaDB 11.4 (Port 3307), weil 5.3 11.4
verlangt.

Hinweise unter PHPUnit 11 (5.0–5.3): 19 „PHPUnit Deprecations“. Sie betreffen Metadaten in Doc-Kommentaren
(`@covers`, `@dataProvider`), die PHPUnit 12 nicht mehr liest. Attribute sind nicht möglich, solange Moodle 4.5
mit PHPUnit 9.6 unterstützt wird. Keine sonstigen Warnungen oder Hinweise.

Nicht geprüft: MySQL, MSSQL, Aurora, andere PHP-Versionen als 8.3.

## Performance (`tests/performance_test.php`)

Eine Kursseite mit 10, 100 oder 500 Aktivitäten, jede mit 9 AUC-Bedingungen (eine je Datenquelle), für eine
Person aufgebaut. Gemessen: Lesezugriffe auf die Datenbank, ohne Beschränkung / mit Beschränkung, sowie die Zeit.

| Moodle | Datenbank | 10 Aktivitäten | 100 Aktivitäten | 500 Aktivitäten |
|---|---|---|---|---|
| 4.5 | PostgreSQL | 16 / 22 (+6), 0,009 s / 0,016 s | 102 / 111 (+9), 0,016 s / 0,076 s | 502 / 511 (+9), 0,123 s / 0,432 s |
| 5.0 | PostgreSQL | 16 / 22 (+6), 0,009 s / 0,020 s | 102 / 111 (+9), 0,023 s / 0,101 s | 502 / 511 (+9), 0,117 s / 0,327 s |
| 5.1 | PostgreSQL | 16 / 22 (+6), 0,007 s / 0,019 s | 102 / 111 (+9), 0,040 s / 0,044 s | 502 / 511 (+9), 0,109 s / 0,289 s |
| 5.2 | PostgreSQL | 16 / 22 (+6), 0,007 s / 0,022 s | 102 / 111 (+9), 0,046 s / 0,061 s | 502 / 511 (+9), 0,142 s / 0,409 s |
| 5.3 | PostgreSQL | 16 / 22 (+6), 0,006 s / 0,017 s | 102 / 111 (+9), 0,020 s / 0,092 s | 502 / 511 (+9), 0,136 s / 0,363 s |
| 4.5 | MariaDB | 12 / 20 (+8), 0,006 s / 0,013 s | 100 / 109 (+9), 0,022 s / 0,065 s | 500 / 509 (+9), 0,089 s / 0,361 s |
| 5.0 | MariaDB | 12 / 20 (+8), 0,006 s / 0,020 s | 100 / 109 (+9), 0,028 s / 0,085 s | 500 / 509 (+9), 0,092 s / 0,429 s |
| 5.1 | MariaDB | 12 / 20 (+8), 0,008 s / 0,016 s | 100 / 109 (+9), 0,023 s / 0,081 s | 500 / 509 (+9), 0,126 s / 0,423 s |
| 5.2 | MariaDB | 12 / 20 (+8), 0,007 s / 0,014 s | 100 / 109 (+9), 0,030 s / 0,107 s | 500 / 509 (+9), 0,103 s / 0,387 s |
| 5.3 | MariaDB 11.4 | 12 / 20 (+8), 0,006 s / 0,017 s | 100 / 109 (+9), 0,074 s / 0,109 s | 500 / 509 (+9), 0,185 s / 0,444 s |

Ergebnis: AUC fügt **höchstens 9 Lesezugriffe pro Seite** hinzu (einen je Merkmal), unabhängig von der Zahl
der Aktivitäten. Die Zunahme um etwa einen Zugriff pro Aktivität entsteht in Moodle selbst, auch ohne
jede Beschränkung. Die Zeit steigt mit der Zahl der Auswertungen (500 × 9 = 4500 Bedingungen in < 0,5 s).
Zeiten sind Einzelmessungen in einem geteilten Container und nur als Größenordnung zu lesen. Nicht gemessen:
parallele Seitenaufrufe, viele Teilnehmende gleichzeitig, Produktionshardware.

## Behat (Chrome 156 headless, ChromeDriver 156, Apache 2.4 + mod_php)

| Moodle | Datenbank | Szenarien | Schritte | Ergebnis |
|---|---|---|---|---|
| 4.5.15+ | PostgreSQL 16.15 | 5 | 62 | bestanden |
| 5.3+ | PostgreSQL 17.11 | 5 | 62 | bestanden |

Szenarien: Speichern und Wiederöffnen im Editor inklusive Axe-Prüfung (Moodle-Standard WCAG 2.0–2.2 A und AA, zusätzlich 1.3.1, 1.4.1, 4.1.2) des
Restrict-access-Editors; im Editor angelegte Regel lässt die passende Teilnehmerin hinein und sperrt die andere
aus, auch beim direkten Aufruf; Hinweistext mit Schwellenwert; Identitätsregel ohne Wert für Teilnehmende;
Anzeige von Zeitpunkten an beiden Sommerzeitwechseln 2026 in der Zeitzone der Lehrperson.
Nicht ausgeführt: Behat auf 5.0–5.2 und auf MariaDB.

## Weitere Gates

| Gate | Ergebnis |
|---|---|
| phpcs moodle-cs (wie CI, `--max-warnings 0`) | 0 Fehler, 0 Warnungen |
| `php -l` aller PHP-Dateien | fehlerfrei |
| Editor-Zeitzonen-Test `node tests/js/form_tz_test.js` | 13 Prüfungen bestanden |
| `python3 tools/build_yui.py --check` | `yui/build` entspricht `yui/src`; Negativprobe (geänderte Quelle) schlägt an |
| Zeitquellen-Gate `clock_usage_test`, `clock_source_test` | bestanden (in jeder Matrixzeile), Negativproben PHP und JS bestanden |
| Linkprüfung `tools/check_links.py` am entpackten Release-ZIP | siehe Abschnitt Release |
| Upgrade 0.2.0 → 0.3.0 auf einer 4.5-Instanz mit vorhandener Regel | erfolgreich, 18 Ereignis-Observer registriert, Regel unverändert |
| Moodle PHPDoc-Check (`local_moodlecheck`) | **nicht lokal ausgeführt** (läuft in der CI) |
| `moodle-plugin-ci validate/savepoints` | **nicht lokal ausgeführt** (läuft in der CI) |
