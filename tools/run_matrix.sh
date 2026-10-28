#!/usr/bin/env bash
# Run the PHPUnit suite over the local test matrix and write a report with versions and results.
#
#   tools/run_matrix.sh [report-dir]
#   AUC_COMBOS="500:pgsql" tools/run_matrix.sh dir   # only some combinations
#
# Matrix: Moodle 4.5, 5.0, 5.1, 5.2, 5.3 on PostgreSQL; 4.5, 5.0, 5.1, 5.2 on MariaDB 10.11 and 5.3 on MariaDB
# 11.4 (port 3307, see docs/environment-setup.md). Each combination is run independently; a failure does not stop
# the others. The report lists every combination as passed, failed or not run.
set -uo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$SRC/build/matrix}"
mkdir -p "$OUT"
REPORT="$OUT/report.md"
rm -f "$OUT/perf.md"
COMBOS="${AUC_COMBOS:-405:pgsql 500:pgsql 501:pgsql 502:pgsql 503:pgsql 405:mariadb 500:mariadb 501:mariadb 502:mariadb 503:mariadb}"

{
  echo "# PHPUnit matrix"
  echo
  echo "Plugin: $(grep -o "release *= *'[^']*'" "$SRC/version.php" | grep -o "'.*'") ($(grep -o 'version *= *[0-9]*' "$SRC/version.php" | grep -o '[0-9]*$'))"
  echo "PHP: $(php -r 'echo PHP_VERSION;')"
  echo
  echo "| Moodle | Datenbank | PHPUnit | Tests | Ergebnis |"
  echo "|---|---|---|---|---|"
} > "$REPORT"

for combo in $COMBOS; do
  t="${combo%%:*}"; db="${combo##*:}"
  log="$OUT/phpunit-$t-$db.log"
  AUC_DB="$db" AUC_JUNIT="$OUT" AUC_PERF_REPORT="$OUT/perf.md" "$SRC/tools/mirror_and_test.sh" "$t" > "$log" 2>&1
  status=$?
  moodle=$(grep -m1 -o 'Moodle [0-9.]*+\? (Build: [0-9]*)' "$log" || echo "Moodle $t")
  dbver=$(grep -m1 -o '\(pgsql\|mariadb\): [^ ,]*' "$log" || echo "$db")
  phpunit=$(grep -m1 -o 'PHPUnit [0-9.]*' "$log" || echo "-")
  summary=$(grep -E '^(OK \(|Tests: )' "$log" | tail -1)
  if [ $status -eq 0 ]; then result="bestanden"; else result="**fehlgeschlagen** (Exit $status)"; fi
  [ -z "$summary" ] && summary="nicht gelaufen, siehe $(basename "$log")"
  echo "| $moodle | $dbver | $phpunit | $summary | $result |" >> "$REPORT"
done
if [ -f "$OUT/perf.md" ]; then
  {
    echo
    echo "## Performance (tests/performance_test.php)"
    echo
    echo "Lesezugriffe beim Aufbau einer Kursseite: ohne Beschränkung / mit 10 Bedingungen je Aktivität (davon eine relative) (+ durch das Plugin), Zeit ohne / mit."
    echo
    echo "| Moodle | Datenbank | 10 Aktivitäten | 100 Aktivitäten | 500 Aktivitäten |"
    echo "|---|---|---|---|---|"
    cat "$OUT/perf.md"
  } >> "$REPORT"
fi
echo >> "$REPORT"
echo "Erzeugt mit tools/run_matrix.sh; JUnit-XML und Logs liegen neben diesem Bericht." >> "$REPORT"
cat "$REPORT"
