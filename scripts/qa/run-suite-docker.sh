#!/usr/bin/env bash
# Run a BrowserQA Dusk suite INSIDE Docker and produce reports.
# Called by make.bat via: docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh <suite> <test-path>
#
# Arguments:
#   $1  suite label  e.g. admin, therapist, student, finance, e2e, smoke
#   $2  test path    e.g. tests/BrowserQA/Admin/
#
# Output files (all relative to project root /var/www/html/):
#   app/qa/reports/<suite>-YYYY-MM-DD-HHMM.md
#   app/qa/reports/<suite>-YYYY-MM-DD-HHMM.html

set -uo pipefail

SUITE="${1:-admin}"
TEST_PATH="${2:-tests/BrowserQA/Admin/}"

ROOT="/var/www/html"
APP="${ROOT}/app"
STAMP="$(date +%Y-%m-%d-%H%M)"
BASE="${SUITE}-${STAMP}"
JUNIT_FILE="${APP}/tests/ci-reports/${BASE}.xml"
REPORT_DIR="${ROOT}/app/qa/reports"

mkdir -p "${REPORT_DIR}" "${APP}/tests/ci-reports"

echo ""
echo "========================================"
echo "  LD Expert Bird - QA Test Suite"
echo "  Suite  : ${SUITE}"
echo "  Target : ${TEST_PATH}"
echo "  Report : app/qa/reports/${BASE}.md"
echo "========================================"
echo ""

# ── 1. Start ChromeDriver ──────────────────────────────────────────────────
echo "[1/3] Starting ChromeDriver..."
"${APP}/vendor/laravel/dusk/bin/chromedriver-linux" --port=9515 > /tmp/chromedriver.log 2>&1 &
CHROMEDRIVER_PID=$!

for i in {1..10}; do
  if curl -s http://localhost:9515/status > /dev/null 2>&1; then
    echo "      ChromeDriver ready on port 9515"
    break
  fi
  sleep 1
done

# ── 2. Run Dusk tests ──────────────────────────────────────────────────────
echo "[2/3] Running Dusk tests..."
cd "${APP}"
DUSK_EXIT=0
php -d memory_limit=512M artisan dusk "${TEST_PATH}" --env=testing --log-junit "${JUNIT_FILE}" || DUSK_EXIT=$?

# ── 3. Kill ChromeDriver ───────────────────────────────────────────────────
kill "${CHROMEDRIVER_PID}" 2>/dev/null || true
wait "${CHROMEDRIVER_PID}" 2>/dev/null || true
echo "      ChromeDriver stopped"

# ── 4. Generate Markdown + HTML report ────────────────────────────────────
echo "[3/3] Generating Markdown + HTML report..."
if [[ -f "${JUNIT_FILE}" ]]; then
  python3 "${ROOT}/scripts/ci/generate-dusk-html-report.py" \
    --junit "${JUNIT_FILE}" \
    --output "${REPORT_DIR}/${BASE}.html" \
    --md    "${REPORT_DIR}/${BASE}.md" || true
  echo "      Written: app/qa/reports/${BASE}.md"
  echo "      Written: app/qa/reports/${BASE}.html"
else
  echo "      WARNING: JUnit XML not found at ${JUNIT_FILE} — skipping report"
fi

echo ""
echo "========================================"
echo "  DONE"
echo "  app/qa/reports/${BASE}.md"
echo "  app/qa/reports/${BASE}.html"
echo "  Exit code: ${DUSK_EXIT}"
echo "========================================"
echo ""

exit "${DUSK_EXIT}"
