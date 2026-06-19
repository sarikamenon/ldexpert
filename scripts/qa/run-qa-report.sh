#!/usr/bin/env bash
# Run a BrowserQA Dusk suite inside Docker and write a timestamped report PAIR
# (.md + .html) into app/qa/reports/. Both files are generated from the same Dusk
# JUnit XML, so they always agree. Each run appends a new pair — the timestamp in
# the filename means runs never overwrite one another.
#
# Usage: scripts/qa/run-qa-report.sh <suite-label> <dusk-target...>
#   scripts/qa/run-qa-report.sh e2e       tests/BrowserQA/E2E/
#   scripts/qa/run-qa-report.sh therapist tests/BrowserQA/Therapist/
#   scripts/qa/run-qa-report.sh admin     tests/BrowserQA/Admin/
#   scripts/qa/run-qa-report.sh finance   tests/BrowserQA/Finance/
#   scripts/qa/run-qa-report.sh student   tests/BrowserQA/Student/
#   scripts/qa/run-qa-report.sh smoke     "tests/BrowserQA/ --group=smoke"
#
# Output: app/qa/reports/<suite>-YYYY-MM-DD-HHMM.md and .html
# Exit code mirrors the Dusk run (report is still produced on test failures).
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT_DIR}"

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <suite-label> <dusk-target...>" >&2
  echo "Example: $0 therapist tests/BrowserQA/Therapist/" >&2
  exit 2
fi

SUITE="$1"; shift
DUSK_TARGET="$*"   # for display only — the real args are forwarded via "$@"

COMPOSE="docker compose"
STAMP="$(date +%Y-%m-%d-%H%M)"
BASE="${SUITE}-${STAMP}"

# The container WORKDIR is /var/www/html/app, which is mounted from the host's
# app/ directory — so tests/ci-reports/<base>.xml inside the container is
# app/tests/ci-reports/<base>.xml on the host. That dir is gitignored scratch;
# only the app/qa/reports/*.{md,html} pair is committed.
CONTAINER_JUNIT="tests/ci-reports/${BASE}.xml"
HOST_JUNIT="app/tests/ci-reports/${BASE}.xml"
REPORT_DIR="app/qa/reports"

mkdir -p "${ROOT_DIR}/${REPORT_DIR}" "${ROOT_DIR}/app/tests/ci-reports"

# Pre-run validation: check if test files exist in the target directory
# Extract path from DUSK_TARGET (first token, before any flags like --group=smoke)
DUSK_PATH="${DUSK_TARGET%% *}"
TEST_COUNT=$(find "app/${DUSK_PATH}" -maxdepth 2 -name '*BrowserTest.php' 2>/dev/null | wc -l)

if [[ ${TEST_COUNT} -eq 0 ]]; then
  echo "❌ No test files found in app/${DUSK_PATH}" >&2
  echo "   Run '/qa-generate-tests' first to generate PHP test files." >&2
  exit 1
fi

echo "==> Found ${TEST_COUNT} test files in ${DUSK_PATH}"

DUSK_EXIT=0

echo "==> Checking .env.testing configuration..."
if ! grep -q "^DB_DATABASE=bird_test" "${ROOT_DIR}/app/.env.testing" 2>/dev/null; then
  echo "⚠️  WARNING: .env.testing may not have DB_DATABASE=bird_test configured" >&2
fi

echo "==> Verifying test database configuration..."
${COMPOSE} exec -T app bash -lc '
  set -uo pipefail
  DB_NAME=$(php artisan tinker --execute "echo config(\"database.connections.mysql.database\");" 2>/dev/null || echo "")
  if [[ "${DB_NAME}" != "bird_test" ]]; then
    echo "" >&2
    echo "❌ CRITICAL: Test database is NOT bird_test!" >&2
    echo "   Configured: ${DB_NAME}" >&2
    echo "   Required:   bird_test" >&2
    echo "" >&2
    echo "🚨 SAFETY SHUTDOWN: Refusing to run tests against non-test database!" >&2
    echo "" >&2
    echo "Fix: Ensure .env.testing sets DB_DATABASE=bird_test" >&2
    echo "     Then restart Docker: docker compose restart app" >&2
    echo "" >&2
    exit 1
  fi
  echo "✓ Confirmed test database: bird_test (safe to proceed)"
'

echo "==> Refreshing test database with fresh seed (bird_test)..."
${COMPOSE} exec -T app bash -lc 'php artisan migrate:fresh --seed --env=testing --force'

echo "==> Verifying seeded admin exists..."
ADMIN_EXISTS=$(${COMPOSE} exec -T app bash -lc \
  'php artisan tinker --execute "echo \App\Models\User::where(\"email\", \"develop.ldexpert@gmail.com\")->exists() ? \"1\" : \"0\";"' 2>/dev/null)

if [[ "${ADMIN_EXISTS}" != "1" ]]; then
  echo "❌ Seeded admin (develop.ldexpert@gmail.com) not found after migrate:fresh" >&2
  echo "   Check if database seeder is properly configured." >&2
  exit 1
fi

echo "✅ Seeded admin verified - database ready for fresh tests"

echo "==> Cleaning up old screenshots..."
rm -rf "${ROOT_DIR}/app/tests/BrowserQA/storage"/* 2>/dev/null || true

echo "==> Starting ChromeDriver and running Dusk tests..."
# Start ChromeDriver and Dusk tests in the same session so ChromeDriver stays alive
${COMPOSE} exec -T app bash -lc '
  set -uo pipefail

  # Start ChromeDriver in background
  /var/www/html/app/vendor/laravel/dusk/bin/chromedriver-linux --port=9515 > /tmp/chromedriver.log 2>&1 &
  CHROMEDRIVER_PID=$!

  # Wait for ChromeDriver to be ready (max 10 seconds)
  for i in {1..10}; do
    if curl -s http://localhost:9515/status > /dev/null 2>&1; then
      echo "✓ ChromeDriver is ready on port 9515"
      break
    fi
    sleep 1
  done

  # Run Dusk tests
  cd /var/www/html/app
  mkdir -p tests/ci-reports
  php -d memory_limit=512M artisan dusk "$@" --env=testing --log-junit "'"${CONTAINER_JUNIT}"'"
  TEST_EXIT=$?

  # Cleanup ChromeDriver
  kill $CHROMEDRIVER_PID 2>/dev/null || true
  wait $CHROMEDRIVER_PID 2>/dev/null || true

  exit $TEST_EXIT
' _ "$@" || DUSK_EXIT=$?

echo "==> Generating report pair from JUnit XML..."
python3 "${ROOT_DIR}/scripts/ci/generate-dusk-html-report.py" \
  --junit "${ROOT_DIR}/${HOST_JUNIT}" \
  --output "${ROOT_DIR}/${REPORT_DIR}/${BASE}.html" \
  --md "${ROOT_DIR}/${REPORT_DIR}/${BASE}.md" || true

echo "==> Cleaning up screenshots after report generation..."
rm -rf "${ROOT_DIR}/app/tests/BrowserQA/storage"/* 2>/dev/null || true

echo "==> Final cleanup: wiping test database and reseeding..."
${COMPOSE} exec -T app bash -lc 'php artisan migrate:fresh --seed --env=testing --force' > /dev/null 2>&1

echo "==> Database reset to fresh state (106 seeded users)"

echo "==> Wrote:"
echo "    ${REPORT_DIR}/${BASE}.md"
echo "    ${REPORT_DIR}/${BASE}.html"
echo "==> Dusk exit code: ${DUSK_EXIT}"
exit "${DUSK_EXIT}"
