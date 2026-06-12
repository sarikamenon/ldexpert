#!/usr/bin/env bash
# HTTP latency matrix for the schedules + session_logs load test.
#
# Usage:
#   BASE_URL=http://localhost:8080 ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD=password \
#     ./scripts/load-test/run-http.sh [iterations]
#
# Output: CSV (endpoint,variant,iteration,http_code,seconds) on stdout —
# redirect to a file and aggregate with collect.awk.
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"
ADMIN_EMAIL="${ADMIN_EMAIL:?set ADMIN_EMAIL}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:?set ADMIN_PASSWORD}"
ITERATIONS="${1:-10}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

# --- login (CSRF + session cookie) -----------------------------------------
TOKEN=$(curl -sc "$JAR" "$BASE_URL/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -sb "$JAR" -c "$JAR" -o /dev/null -w "" -X POST "$BASE_URL/login" \
    --data-urlencode "_token=$TOKEN" \
    --data-urlencode "email=$ADMIN_EMAIL" \
    --data-urlencode "password=$ADMIN_PASSWORD"
CSRF=$(curl -sb "$JAR" -c "$JAR" "$BASE_URL/admin/dashboard" | grep -o 'name="csrf-token" content="[^"]*"' | head -1 | sed 's/.*content="//;s/"//')
[ -n "$CSRF" ] || { echo "login failed — no csrf token on dashboard" >&2; exit 1; }

dt_post() { # name path extra_params
    local name="$1" path="$2" extra="$3" i code secs
    for variant in "page1|draw=1&start=0&length=25" \
                   "deep|draw=1&start=100000&length=25" \
                   "search|draw=1&start=0&length=25&search%5Bvalue%5D=smith"; do
        local vname="${variant%%|*}" vparams="${variant#*|}"
        for i in $(seq 1 "$ITERATIONS"); do
            read -r code secs < <(curl -sb "$JAR" -o /dev/null -w "%{http_code} %{time_total}" \
                -X POST "$BASE_URL$path" \
                -H "X-CSRF-TOKEN: $CSRF" -H "X-Requested-With: XMLHttpRequest" \
                --data "$vparams$extra")
            echo "$name,$vname,$i,$code,$secs"
        done
    done
}

get_page() { # name path
    local name="$1" path="$2" i code secs
    for i in $(seq 1 "$ITERATIONS"); do
        read -r code secs < <(curl -sb "$JAR" -o /dev/null -w "%{http_code} %{time_total}" "$BASE_URL$path")
        echo "$name,page,$i,$code,$secs"
    done
}

echo "endpoint,variant,iteration,http_code,seconds"

# DataTables POST endpoints over the two volume tables
dt_post "admin.session-logs.data"      "/admin/session-logs/data"      ""
dt_post "admin.students.data"          "/admin/students/data"          ""
dt_post "admin.ssas.data"              "/admin/ssas/data"              ""

# Page renders that aggregate over schedules/session_logs
get_page "admin.dashboard"             "/admin/dashboard"
get_page "admin.session-logs.index"    "/admin/session-logs"
get_page "admin.reports.utilization"   "/admin/reports/ssa-utilization"
get_page "admin.analytics.overview"    "/admin/analytics"

echo "done — aggregate with: awk -f scripts/load-test/collect.awk results.csv" >&2
