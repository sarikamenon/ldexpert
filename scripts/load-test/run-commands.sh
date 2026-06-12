#!/usr/bin/env bash
# Times + peak memory for the command-layer surfaces affected by 1M-row
# schedules/session_logs. Run from the repo root.
#
#   ./scripts/load-test/run-commands.sh [db_name]
set -euo pipefail

DB="${1:-bird_test}"
EXEC="docker compose exec -T app bash -lc"

run_timed() { # label command
    echo "=== $1"
    $EXEC "cd /var/www/html/app && export DB_DATABASE=$DB DB_HOST=mysql DB_PORT=3306 && /usr/bin/time -v php artisan $2 2>&1 | tail -25" \
        | grep -E "Elapsed|Maximum resident|^[^\s]" | head -12 || true
    echo
}

run_timed "billing:generate (dry-run, full sweep)" "billing:generate --dry-run"
run_timed "makeup-reminders:generate"              "makeup-reminders:generate"
run_timed "ledger:verify"                          "ledger:verify"
