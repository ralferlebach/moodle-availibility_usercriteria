#!/usr/bin/env bash
# Mirror this plugin into the Moodle trees under /home/claude and run its PHPUnit suite there.
#
# Never develop in the mirror: it is overwritten on every run (see docs/environment-setup.md).
#
#   tools/mirror_and_test.sh                  # 4.5 and 5.3 on PostgreSQL
#   tools/mirror_and_test.sh 405              # one tree only
#   AUC_DB=mariadb tools/mirror_and_test.sh 405 500
#   AUC_JUNIT=/path/dir tools/mirror_and_test.sh ...   # also write JUnit XML per tree
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGETS="${1:-405 503}"
shift || true
TARGETS="$TARGETS $*"
DBKIND="${AUC_DB:-pgsql}"

# Start the databases (they do not survive a container restart).
pg_ctlcluster 16 main start 2>/dev/null || true
pg_ctlcluster 17 main start 2>/dev/null || true
if [ "$DBKIND" = "mariadb" ]; then service mariadb start >/dev/null 2>&1 || true; fi

for t in $TARGETS; do
  root="/home/claude/moodle${t}"
  if [ -d "$root/public" ]; then
    dest="$root/public/availability/condition/usercriteria"; init="public/admin/tool/phpunit/cli/init.php"
  else
    dest="$root/availability/condition/usercriteria"; init="admin/tool/phpunit/cli/init.php"
  fi
  echo "=== Moodle $t ($DBKIND) ==="
  rm -rf "$dest"
  mkdir -p "$dest"
  tar -C "$SRC" --exclude=.git --exclude=node_modules -cf - . | tar -C "$dest" -xf -
  junit=()
  if [ -n "${AUC_JUNIT:-}" ]; then mkdir -p "$AUC_JUNIT"; junit=(--log-junit "$AUC_JUNIT/phpunit-$t-$DBKIND.xml"); fi
  (cd "$root" && AUC_DB="$DBKIND" php "$init" >/dev/null \
    && AUC_DB="$DBKIND" vendor/bin/phpunit --testsuite availability_usercriteria_testsuite "${junit[@]}")
done
