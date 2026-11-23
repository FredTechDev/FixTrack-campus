#!/usr/bin/env bash
# FixTrack Campus - automated smoke test.
# Requires the Docker stack (or any running instance) at $BASE_URL.
set -uo pipefail

BASE_URL="${BASE_URL:-http://localhost:8085}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
FAILURES=0

pass() { printf '  \033[0;32mPASS\033[0m  %s\n' "$1"; }
fail() { printf '  \033[0;31mFAIL\033[0m  %s\n' "$1"; FAILURES=$((FAILURES + 1)); }

login() {
    local jar="$1" email="$2" password="$3"
    local token
    token=$(curl -s -c "$jar" "$BASE_URL/login.php" \
        | grep -o 'name="csrf_token" value="[^"]*"' | head -1 \
        | sed 's/.*value="//;s/"//')
    if [ -z "$token" ]; then
        fail "Could not obtain CSRF token from login page"
        return 1
    fi
    curl -s -b "$jar" -c "$jar" \
        -d "csrf_token=${token}&email=${email}&password=${password}" \
        -o /dev/null "$BASE_URL/login.php"
}

check_page() {
    local jar="$1" path="$2" code body
    body="$TMP_DIR/body.html"
    code=$(curl -s -b "$jar" -o "$body" -w '%{http_code}' "$BASE_URL/$path")
    if [ "$code" != "200" ]; then
        fail "$path (HTTP $code)"
        return
    fi
    if grep -qiE 'fatal error|parse error|<b>warning:</b>|<b>notice:</b>|<b>deprecated:</b>' "$body"; then
        fail "$path (PHP error in output)"
        return
    fi
    pass "$path"
}

check_status() {
    local label="$1" expected="$2" actual="$3"
    if [ "$expected" = "$actual" ]; then pass "$label (HTTP $actual)"; else fail "$label (expected $expected, got $actual)"; fi
}

echo "==> Logging in"
A="$TMP_DIR/admin.cookies"; S="$TMP_DIR/student.cookies"; T="$TMP_DIR/tech.cookies"
login "$A" 'admin@fixtrack.campus' 'Admin@123'
login "$S" 'alice.student@fixtrack.campus' 'Student@123'
login "$T" 'tech.electrical@fixtrack.campus' 'Tech@123'

echo "==> Public pages"
for p in index.php login.php register.php; do check_page '' "$p"; done

echo "==> Student pages"
for p in student/dashboard.php student/reports.php student/report-new.php \
         student/report-view.php?id=1 notifications.php qr/report.php qr/facility.php?id=1; do
    check_page "$S" "$p"
done

echo "==> Admin pages"
for p in admin/dashboard.php admin/reports.php admin/report-view.php?id=1 \
         admin/users.php admin/facilities.php admin/categories.php admin/analytics.php \
         admin/qr-codes.php; do
    check_page "$A" "$p"
done

echo "==> Technician pages"
for p in technician/dashboard.php technician/jobs.php technician/job-view.php?id=1; do
    check_page "$T" "$p"
done

echo "==> Access control"
check_status 'Student blocked from admin' 403 "$(curl -s -b "$S" -o /dev/null -w '%{http_code}' "$BASE_URL/admin/reports.php")"
check_status 'Technician blocked from admin' 403 "$(curl -s -b "$T" -o /dev/null -w '%{http_code}' "$BASE_URL/admin/users.php")"
check_status 'Guest redirected from dashboard' 302 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/student/dashboard.php")"

echo "==> CSRF"
TOKEN=$(curl -s -b "$A" "$BASE_URL/admin/report-view.php?id=1" \
    | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
check_status 'POST without CSRF token' 403 \
    "$(curl -s -b "$A" -o /dev/null -w '%{http_code}' -d 'report_id=1&decision=approve' "$BASE_URL/actions/report-review.php")"
check_status 'POST with forged token' 403 \
    "$(curl -s -b "$A" -o /dev/null -w '%{http_code}' -d "csrf_token=invalid&report_id=1&decision=approve" "$BASE_URL/actions/report-review.php")"

echo "==> Export"
for jar in "$A" "$S"; do
    code=$(curl -s -b "$jar" -o "$TMP_DIR/export.csv" -w '%{http_code}' "$BASE_URL/export.php")
    if [ "$code" = "200" ] && head -c 200 "$TMP_DIR/export.csv" | grep -qi 'Tracking Code'; then
        pass "CSV export has headers"
    else
        fail "CSV export (HTTP $code)"
    fi
done

echo
if [ "$FAILURES" -eq 0 ]; then
    echo "All smoke tests passed."
    exit 0
else
    echo "$FAILURES test(s) failed."
    exit 1
fi
