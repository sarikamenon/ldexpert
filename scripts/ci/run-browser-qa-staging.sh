#!/usr/bin/env bash
# Run the BrowserQA Dusk suite directly on the staging / test server (NO Docker),
# against the DEDICATED `bird_test` DATABASE ONLY.
#
# ╔════════════════════════════════════════════════════════════════════════════╗
# ║ CRITICAL SAFETY REQUIREMENT                                               ║
# ║                                                                            ║
# ║ This script is HARDENED to work ONLY with the bird_test database.         ║
# ║                                                                            ║
# ║ • It checks that .env.testing has DB_DATABASE=bird_test (ABORTS if not)   ║
# ║ • It verifies at runtime that the configured DB is bird_test (ABORTS if   ║
# ║   runtime config differs, e.g. due to env var override)                   ║
# ║ • It will WIPE and re-seed bird_test via migrate:fresh --seed            ║
# ║                                                                            ║
# ║ These guards protect production and staging data from accidental loss.    ║
# ║ They CANNOT be bypassed — if they fail, the test run aborts.             ║
# ╚════════════════════════════════════════════════════════════════════════════╝
#
# The CI workflow (.github/workflows/browser-qa-staging.yml) SSHes into the test
# server, drops this script in /tmp, and runs it. It produces per-role JUnit XML
# (+ failure screenshots) under OUT_DIR; the workflow scp's those back to the
# GitHub runner and builds the HTML report there (so the server needs no Python).
#
# Implementation: the suite is run in testing mode against `bird_test` ONLY. The
# live staging app and its database are never reconfigured — we boot a SEPARATE
# testing-mode instance via `php artisan serve` on a local port.
set -euo pipefail

# ── Configuration (override via env when invoking) ───────────────────────────
# Path to the deployed Laravel app on the test server.
# ADJUST to match the real deploy path used by deploy_ld_expert_bird.sh.
APP_DIR="${APP_DIR:-/var/www/ld-expert-bird/app}"
# Local port the testing-mode app is served on for Dusk to drive.
SERVE_PORT="${SERVE_PORT:-8090}"
# Where reports/screenshots are collected for the workflow to fetch.
OUT_DIR="${OUT_DIR:-/tmp/browserqa-reports}"
# Reuse the assets deploy_staging already built (1) or rebuild them here (0).
# Default 1 so we never overwrite the live staging public/build manifest.
SKIP_ASSETS="${SKIP_ASSETS:-1}"
# Dusk test groups (subdirectories of tests/BrowserQA/).
ROLES=(Admin Therapist Student Finance E2E Smoke)

REPORT_DIR="tests/ci-reports"
SERVE_PID=""
DUSK_EXIT=0

cleanup() {
  if [[ -n "${SERVE_PID}" ]] && kill -0 "${SERVE_PID}" 2>/dev/null; then
    echo "==> Stopping testing-mode app server (pid ${SERVE_PID})..."
    kill "${SERVE_PID}" 2>/dev/null || true
  fi
}
trap cleanup EXIT

cd "${APP_DIR}"

# ── CRITICAL SAFETY CHECK: bird_test database ONLY ────────────────────────
echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║ CRITICAL: BrowserQA runs against bird_test database ONLY      ║"
echo "║                                                                ║"
echo "║ This script will:                                              ║"
echo "║   1. Verify .env.testing targets DB_DATABASE=bird_test        ║"
echo "║   2. Refuse to run if configured for any other database       ║"
echo "║   3. WIPE and re-seed bird_test (migrate:fresh --seed)        ║"
echo "║                                                                ║"
echo "║ This PROTECTS production/staging data from accidental loss.   ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

echo "==> FIRST SAFETY CHECK: Verifying .env.testing exists and targets bird_test..."
if [[ ! -f .env.testing ]]; then
  echo "" >&2
  echo "❌ CRITICAL FAILURE: .env.testing not found in ${APP_DIR}" >&2
  echo "" >&2
  echo "   The test server MUST have a .env.testing file configured with:" >&2
  echo "     DB_DATABASE=bird_test" >&2
  echo "     DB_HOST=<server-db-host>" >&2
  echo "     DB_USERNAME=<db-user>" >&2
  echo "     DB_PASSWORD=<db-password>" >&2
  echo "" >&2
  exit 1
fi

# Hard safety guard: refuse to run unless .env.testing targets bird_test.
if ! grep -Eq '^DB_DATABASE=bird_test[[:space:]]*$' .env.testing; then
  echo "" >&2
  echo "╔════════════════════════════════════════════════════════════════╗" >&2
  echo "║ 🚨 CRITICAL FAILURE: NOT CONFIGURED FOR bird_test 🚨           ║" >&2
  echo "╚════════════════════════════════════════════════════════════════╝" >&2
  echo "" >&2
  echo "The .env.testing file does NOT set DB_DATABASE=bird_test." >&2
  echo "" >&2
  echo "Current configuration:" >&2
  grep -E '^DB_DATABASE=' .env.testing >&2 || echo "  (DB_DATABASE not set at all)" >&2
  echo "" >&2
  echo "REFUSING TO RUN TESTS to protect production/staging data." >&2
  echo "" >&2
  echo "Fix: Update .env.testing to set DB_DATABASE=bird_test" >&2
  echo "     Then re-run the workflow." >&2
  echo "" >&2
  exit 1
fi

echo "✓ SAFETY CHECK PASSED: .env.testing is configured for bird_test database"
echo ""

# Ensure dev dependencies are present. Dusk (laravel/dusk) is a require-dev
# package, so a server deployed with `composer install --no-dev` will NOT have
# it. This install is idempotent: a no-op when deps are already present, and it
# pulls in Dusk when missing. Runs against the deployed vendor/ in APP_DIR.
echo "==> Ensuring dev dependencies (incl. Dusk) are installed..."
composer install --no-interaction --prefer-dist 2>&1

# Dusk's own env file drives the test PROCESS; point its browser at our local
# testing-mode server. Inherit everything else from .env.testing.
cp -f .env.testing .env.dusk.local
if grep -q '^APP_URL=' .env.dusk.local; then
  sed -i "s|^APP_URL=.*|APP_URL=http://127.0.0.1:${SERVE_PORT}|" .env.dusk.local
else
  echo "APP_URL=http://127.0.0.1:${SERVE_PORT}" >> .env.dusk.local
fi

# Ensure a real APP_KEY exists in both env files (a missing key → 500 on boot).
for f in .env.testing .env.dusk.local; do
  if ! grep -Eq '^APP_KEY=base64:' "$f"; then
    KEY_VALUE="$(php artisan key:generate --show)"
    if grep -q '^APP_KEY=' "$f"; then
      sed -i "s|^APP_KEY=.*|APP_KEY=${KEY_VALUE}|" "$f"
    else
      echo "APP_KEY=${KEY_VALUE}" >> "$f"
    fi
  fi
done

# The test server has no S3 credentials in testing mode; force local disk.
for f in .env.testing .env.dusk.local; do
  if grep -q '^FILESYSTEM_DISK=' "$f"; then
    sed -i 's/^FILESYSTEM_DISK=.*/FILESYSTEM_DISK=local/' "$f"
  else
    echo 'FILESYSTEM_DISK=local' >> "$f"
  fi
done

if [[ "${SKIP_ASSETS}" != "1" ]]; then
  echo "==> Building frontend assets..."
  npm ci 2>&1
  npm run build 2>&1
else
  echo "==> Skipping asset build (SKIP_ASSETS=1) — reusing deployed public/build."
fi

echo "==> Resetting the bird_test database (migrate:fresh --seed, testing env)..."
php -d memory_limit=1024M artisan migrate:fresh --seed --env=testing --force

echo "==> Ensuring matching ChromeDriver is installed..."
php artisan dusk:chrome-driver --detect --no-interaction

echo "==> Clearing caches so the testing-mode server reads .env.testing..."
php artisan config:clear || true
php artisan view:clear || true

echo "==> Starting testing-mode app server on port ${SERVE_PORT}..."
php artisan serve --env=testing --host=127.0.0.1 --port="${SERVE_PORT}" \
  > "${REPORT_DIR%/*}/serve.log" 2>&1 &
SERVE_PID=$!

echo "==> Waiting for the app to respond on http://127.0.0.1:${SERVE_PORT}/login ..."
for i in {1..60}; do
  if curl -s -o /dev/null "http://127.0.0.1:${SERVE_PORT}/login"; then
    break
  fi
  echo "  Waiting... ($i/60)"
  sleep 2
done

HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${SERVE_PORT}/login" || echo 000)"
echo "  /login returned HTTP ${HTTP_CODE}"
if [[ "${HTTP_CODE}" != "200" ]]; then
  echo "❌ Testing-mode app is not serving correctly (expected 200, got ${HTTP_CODE})." >&2
  exit 1
fi
echo "✓ App is serving in testing mode"

echo "==> SECOND SAFETY CHECK: Confirming the live runtime database is bird_test..."
DB_NAME="$(php artisan tinker --env=testing --execute 'echo config("database.connections.mysql.database");' 2>/dev/null || echo '')"
if [[ "${DB_NAME}" != "bird_test" ]]; then
  echo "" >&2
  echo "╔════════════════════════════════════════════════════════════════╗" >&2
  echo "║ 🚨 CRITICAL FAILURE: WRONG DATABASE DETECTED 🚨                ║" >&2
  echo "╚════════════════════════════════════════════════════════════════╝" >&2
  echo "" >&2
  echo "Laravel is configured to run against: '${DB_NAME}'" >&2
  echo "Required for safety: 'bird_test'" >&2
  echo "" >&2
  echo "REFUSING TO RUN TESTS to protect against data loss." >&2
  echo "This is a critical safety gate — not a misconfiguration to bypass." >&2
  echo "" >&2
  exit 1
fi
echo "✓ SAFETY CHECK PASSED: Runtime database confirmed as bird_test"
echo ""

# Guard against a false-green run: if no test files exist, `artisan dusk` finds
# nothing, exits 0, and the pipeline reports success having tested nothing.
echo "==> Checking BrowserQA tests exist..."
TEST_COUNT="$(find tests/BrowserQA -name '*BrowserTest.php' 2>/dev/null | wc -l | tr -d ' \r')"
if [[ "${TEST_COUNT}" == "0" ]]; then
  echo "ERROR: No *BrowserTest.php files under tests/BrowserQA/. Run qa-generate-tests first." >&2
  exit 1
fi
echo "✓ Found ${TEST_COUNT} BrowserQA test file(s)"

echo "==> Running Dusk (tests/BrowserQA/)..."
mkdir -p "${REPORT_DIR}"
for role in "${ROLES[@]}"; do
  echo "==> Running ${role} tests..."
  if ! php -d memory_limit=512M artisan dusk "tests/BrowserQA/${role}" \
        --env=testing --log-junit "${REPORT_DIR}/junit-${role}.xml"; then
    DUSK_EXIT=$?
    echo "  ${role} tests failed with exit code: ${DUSK_EXIT}"
  fi
done

echo "==> Collecting reports + screenshots into ${OUT_DIR}..."
rm -rf "${OUT_DIR}"
mkdir -p "${OUT_DIR}/screenshots"
cp -f "${REPORT_DIR}"/junit-*.xml "${OUT_DIR}/" 2>/dev/null || echo "  (no junit-*.xml produced)"
cp -f tests/BrowserQA/screenshots/* "${OUT_DIR}/screenshots/" 2>/dev/null || true

echo "==> Dusk finished with exit code ${DUSK_EXIT}"
exit "${DUSK_EXIT}"
