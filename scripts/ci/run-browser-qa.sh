#!/usr/bin/env bash
# Run BrowserQA Dusk suite inside Docker and emit JUnit + HTML reports.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT_DIR}"

COMPOSE="docker compose"
REPORT_DIR="app/tests/ci-reports"
JUNIT_PATH="${REPORT_DIR}/junit.xml"
HTML_PATH="${REPORT_DIR}/dusk-report.html"
DUSK_EXIT=0

echo "==> Waiting for MySQL..."
until ${COMPOSE} exec -T mysql mysqladmin ping -h localhost -ubird -psecret --silent 2>/dev/null; do
  sleep 2
done

echo "==> Preparing Laravel env inside app container..."
${COMPOSE} exec -T -u root app bash -lc "
  set -euo pipefail
  cd /var/www/html/app
  cp -n /var/www/html/docker/env/app.env .env 2>/dev/null || cp /var/www/html/docker/env/app.env .env
  cp /var/www/html/docker/env/testing.env .env.testing
  cp /var/www/html/docker/env/dusk.local.env .env.dusk.local
"

echo "==> Installing Composer dependencies..."
${COMPOSE} exec -T -u root app bash -lc "cd /var/www/html/app && composer install --no-interaction --prefer-dist 2>&1" || exit 1

echo "==> Verifying vendor directory..."
${COMPOSE} exec -T -u root app bash -lc "
  if [ ! -d /var/www/html/app/vendor ]; then
    echo '❌ CRITICAL: vendor/ directory missing!'
    echo 'Directory contents:'
    ls -la /var/www/html/app/ | head -20
    exit 1
  fi
  echo '✓ vendor/ directory exists'
"

echo "==> Installing NPM dependencies..."
${COMPOSE} exec -T -u root app bash -lc "cd /var/www/html/app && npm ci 2>&1" || exit 1

echo "==> Building assets..."
${COMPOSE} exec -T -u root app bash -lc "cd /var/www/html/app && npm run build 2>&1" || exit 1

echo "==> Setting up Laravel environment..."
${COMPOSE} exec -T -u root app bash -lc "
  cd /var/www/html/app
  # Generate ONE key with --show (prints to stdout, writes nothing) and put it in
  # ALL env files explicitly. Do NOT use plain 'key:generate' here: the container
  # runs APP_ENV=testing, so Laravel writes the key to .env.testing and leaves
  # .env empty — then copying from .env propagates an EMPTY key. The served app
  # (APP_ENV=testing -> .env.testing) MUST have a real base64 key or every request
  # throws MissingAppKeyException (500).
  APP_KEY_VALUE=\$(php artisan key:generate --show)
  echo \"Generated APP_KEY: \${APP_KEY_VALUE}\"
  for f in .env .env.testing .env.dusk.local; do
    if grep -q '^APP_KEY=' \"\$f\" 2>/dev/null; then
      sed -i \"s|^APP_KEY=.*|APP_KEY=\${APP_KEY_VALUE}|\" \"\$f\"
    else
      echo \"APP_KEY=\${APP_KEY_VALUE}\" >> \"\$f\"
    fi
  done
  # CI has no S3 credentials; force local disk for Dusk.
  for f in .env .env.testing .env.dusk.local; do
    if grep -q '^FILESYSTEM_DISK=' \"\$f\"; then
      sed -i 's/^FILESYSTEM_DISK=.*/FILESYSTEM_DISK=local/' \"\$f\"
    else
      echo 'FILESYSTEM_DISK=local' >> \"\$f\"
    fi
  done

  # Fresh CI checkout: storage/ and bootstrap/cache are owned by the runner user,
  # but PHP-FPM workers run as www-data. Make them writable so Laravel can boot,
  # write logs, compile views, and cache config (otherwise: silent 500, no log file).
  mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
  chmod -R 777 storage bootstrap/cache
  # Clear any stale cached config/routes/views baked into the checkout.
  php artisan config:clear || true
  php artisan cache:clear || true
  php artisan view:clear || true
"

echo "==> Checking if vendor exists BEFORE migrations..."
${COMPOSE} exec -T -u root app bash -lc "
  if [ -d /var/www/html/app/vendor ]; then
    echo '✓ vendor directory EXISTS'
    ls -la /var/www/html/app/vendor | head -5
  else
    echo '❌ vendor directory MISSING!'
    echo 'Contents of /var/www/html/app/:'
    ls -la /var/www/html/app/
    exit 1
  fi
"

# CRITICAL: PHP-FPM workers started BEFORE composer install ran, so they cached
# the missing-vendor state (realpath cache + opcache.validate_timestamps=0).
# Restart app + nginx so fresh workers pick up the now-present vendor/ and assets.
echo "==> Restarting app and nginx so PHP-FPM picks up vendor/ ..."
${COMPOSE} restart app nginx

echo "==> Resetting test database and installing ChromeDriver..."
${COMPOSE} exec -T -u root app bash -lc "
  set -euo pipefail
  cd /var/www/html/app
  # Increase memory limit for memory-intensive operations (migrations + seeding)
  php -d memory_limit=1024M artisan migrate:fresh --seed --env=testing --force
  php artisan dusk:chrome-driver --detect --no-interaction
"

# CRITICAL: the artisan commands above run as ROOT and create files in storage/
# (e.g. storage/logs/laravel.log owned by root:644). The FPM workers run as
# www-data and then can't append → 'Permission denied' 500. Re-open permissions
# AFTER all root-run commands, and clear any config cache so the served app
# (APP_ENV=testing → .env.testing) picks up the APP_KEY we injected earlier.
echo "==> Fixing storage permissions after root-run artisan commands..."
${COMPOSE} exec -T -u root app bash -lc "
  cd /var/www/html/app
  chmod -R 777 storage bootstrap/cache
  # Remove a possibly root-owned log so the next write recreates it as www-data
  rm -f storage/logs/laravel.log
  php artisan config:clear || true
"
# Restart so FPM workers re-read config (APP_KEY) with fresh, writable storage.
${COMPOSE} restart app nginx

echo "==> Waiting for app to be ready..."
# Wait extra time to ensure seeding completes and app initializes
sleep 10
for i in {1..60}; do
  if curl -s http://localhost:8080/login > /dev/null 2>&1; then
    echo "✓ App is ready"
    sleep 5  # Extra stability wait
    break
  fi
  echo "  Waiting... ($i/60)"
  sleep 2
done

# Debug: Confirm the app actually serves a 200 (not just that the container is up)
echo "==> Verifying app responds with HTTP 200..."
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/login || echo 000)"
echo "  /login returned HTTP ${HTTP_CODE}"
if [ "${HTTP_CODE}" != "200" ]; then
  echo "❌ App is not serving correctly (expected 200, got ${HTTP_CODE})"
  echo "=== HTTP response body (APP_DEBUG=true shows the exception) ==="
  curl -s http://localhost:8080/login | head -60
  echo ""
  echo "=== Full app container logs ==="
  ${COMPOSE} logs app | tail -80
  echo "=== Laravel log ==="
  ${COMPOSE} exec -T app bash -lc "tail -60 /var/www/html/app/storage/logs/laravel.log 2>/dev/null || echo 'No log file'" || true
  exit 1
fi
echo "✓ App is serving correctly"

# Capture Laravel error log
echo "==> Laravel application logs:"
${COMPOSE} exec -T app bash -lc "tail -100 /var/www/html/app/storage/logs/laravel.log 2>/dev/null || echo 'No log file'" || true

# Also check PHP error log
echo "==> PHP error log:"
${COMPOSE} exec -T app bash -lc "tail -50 /var/www/html/app/storage/logs/php-error.log 2>/dev/null || echo 'No PHP error log'" || true

echo "==> Checking BrowserQA tests exist..."
TEST_COUNT="$(${COMPOSE} exec -T -u root app bash -lc "find /var/www/html/app/tests/BrowserQA -name '*BrowserTest.php' 2>/dev/null | wc -l" | tr -d ' \r')"
if [[ "${TEST_COUNT}" == "0" ]]; then
  echo "ERROR: No *BrowserTest.php files under tests/BrowserQA/. Run qa-generate-tests first." >&2
  exit 1
fi

echo "==> Verifying test database configuration..."
${COMPOSE} exec -T -u root app bash -lc "
  set -euo pipefail
  cd /var/www/html/app
  DB_NAME=\$(php artisan tinker --execute 'echo config(\"database.connections.mysql.database\");' 2>/dev/null || echo '')
  if [[ \"\${DB_NAME}\" != \"bird_test\" ]]; then
    echo '' >&2
    echo '❌ CRITICAL: Test database is NOT bird_test!' >&2
    echo '   Configured: '\"\${DB_NAME}\" >&2
    echo '   Required:   bird_test' >&2
    echo '' >&2
    echo '🚨 SAFETY SHUTDOWN: Refusing to run tests against non-test database!' >&2
    echo '' >&2
    echo 'Fix: Ensure .env.testing sets DB_DATABASE=bird_test' >&2
    echo '     Then restart Docker: docker compose restart app' >&2
    echo '' >&2
    exit 1
  fi
  echo '✓ Confirmed test database: bird_test (safe to proceed)'
"

echo "==> Running Dusk (tests/BrowserQA/)..."
mkdir -p "${ROOT_DIR}/${REPORT_DIR}"
${COMPOSE} exec -T -u root app bash -lc "
  set -euo pipefail
  cd /var/www/html/app
  mkdir -p tests/ci-reports
  for role in Admin Therapist Student Finance E2E Smoke; do
    echo \"==> Running \$role tests...\"
    if ! php -d memory_limit=512M artisan dusk tests/BrowserQA/\$role --env=testing --log-junit tests/ci-reports/junit-\$role.xml; then
      DUSK_EXIT=\$?
      echo \"Tests failed with exit code: \$DUSK_EXIT\"
    fi
  done
" || DUSK_EXIT=$?

echo "==> Merging per-role JUnit reports into one junit.xml..."
# The per-role junit-*.xml files are bind-mounted to the host. Merge them with
# Python (proper XML parsing — the old grep approach failed because <testsuite>
# blocks span multiple lines, producing an empty junit.xml → no HTML report).
python3 - "${ROOT_DIR}/${REPORT_DIR}" <<'PY' || true
import sys, glob, os
import xml.etree.ElementTree as ET

report_dir = sys.argv[1]
out = ET.Element("testsuites")
files = sorted(glob.glob(os.path.join(report_dir, "junit-*.xml")))
if not files:
    print("No junit-*.xml files found to merge")
    sys.exit(0)
for f in files:
    try:
        root = ET.parse(f).getroot()
    except Exception as e:
        print(f"  skip {os.path.basename(f)}: {e}")
        continue
    if root.tag == "testsuites":
        for ts in root.findall("testsuite"):
            out.append(ts)
    elif root.tag == "testsuite":
        out.append(root)
    print(f"  merged {os.path.basename(f)}")
ET.ElementTree(out).write(os.path.join(report_dir, "junit.xml"),
                          encoding="utf-8", xml_declaration=True)
print("Wrote merged junit.xml")
PY

echo "==> Generating HTML report..."
python3 "${ROOT_DIR}/scripts/ci/generate-dusk-html-report.py" \
  --junit "${ROOT_DIR}/${JUNIT_PATH}" \
  --output "${ROOT_DIR}/${HTML_PATH}" || true

if [[ ! -f "${ROOT_DIR}/${HTML_PATH}" ]]; then
  echo "WARN: HTML report missing at ${HTML_PATH}" >&2
fi

echo "==> Dusk finished with exit code ${DUSK_EXIT}"
exit "${DUSK_EXIT}"
