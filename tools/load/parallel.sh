#!/usr/bin/env bash
# Parallel load on one course page with logged-in students (developer tool, not shipped).
#
#   tools/load/parallel.sh <wwwroot> <courseid> <user1,user2,...> <requests per user> [password]
#
# Logs every user in (one session each), then requests the course page with all users at the same time. Prints the
# number of requests, failures (HTTP status not 200 or a Moodle error page) and the response times (median, 95th
# percentile, maximum) in seconds.
set -euo pipefail
WWW="$1"; COURSE="$2"; USERS="$3"; PER="$4"; PASS="${5:-Load!2345}"
DIR="$(mktemp -d)"
IFS=',' read -ra LIST <<< "$USERS"
for u in "${LIST[@]}"; do
  jar="$DIR/$u.jar"
  token=$(curl -s -c "$jar" "$WWW/login/index.php" | grep -o 'name="logintoken" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$jar" -c "$jar" -o /dev/null --data-urlencode "username=$u" --data-urlencode "password=$PASS" \
    --data-urlencode "logintoken=$token" "$WWW/login/index.php"
done
run_user () {
  local u="$1"
  for i in $(seq 1 "$PER"); do
    curl -s -b "$DIR/$u.jar" -o "$DIR/$u.$i.html" -w "%{http_code} %{time_total}\n" "$WWW/course/view.php?id=$COURSE"
    if ! grep -q "Page 1" "$DIR/$u.$i.html" || grep -q 'class="errorbox' "$DIR/$u.$i.html"; then echo "BADPAGE $u $i"; fi
  done
}
export -f run_user; export DIR WWW COURSE PER
printf '%s\n' "${LIST[@]}" | xargs -P "${#LIST[@]}" -I{} bash -c 'run_user {}' > "$DIR/results.txt"
total=$(grep -c '^[0-9]' "$DIR/results.txt")
bad=$(( $(grep '^[0-9]' "$DIR/results.txt" | grep -vc '^200 ' || true) + $(grep -c '^BADPAGE' "$DIR/results.txt" || true) ))
grep '^[0-9]' "$DIR/results.txt" | awk '{print $2}' | sort -n > "$DIR/times.txt"
n=$(wc -l < "$DIR/times.txt")
p50=$(sed -n "$(( (n + 1) / 2 ))p" "$DIR/times.txt"); p95=$(sed -n "$(( (n * 95 + 99) / 100 ))p" "$DIR/times.txt")
max=$(tail -1 "$DIR/times.txt")
echo "requests=$total failures=$bad users=${#LIST[@]} median=${p50}s p95=${p95}s max=${max}s"
rm -rf "$DIR"
