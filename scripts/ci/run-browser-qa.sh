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
  # CI has no S3 credentials; force local disk for Dusk.
  for f in .env .env.testing .env.dusk.local; do
    if grep -q '^FILESYSTEM_DISK=' \"\$f\"; then
      sed -i 's/^FILESYSTEM_DISK=.*/FILESYSTEM_DISK=local/' \"\$f\"
    else
      echo 'FILESYSTEM_DISK=local' >> \"\$f\"
    fi
  done
  composer install --no-interaction --prefer-dist
  npm ci
  npm run build
  if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
  fi
  APP_KEY=\$(grep '^APP_KEY=' .env | cut -d= -f2-)
  if ! grep -q '^APP_KEY=' .env.dusk.local; then
    echo \"APP_KEY=\${APP_KEY}\" >> .env.dusk.local
  fi
"

echo "==> Resetting test database and installing ChromeDriver..."
${COMPOSE} exec -T -u root app bash -lc "
  set -euo pipefail
  cd /var/www/html/app
  php artisan migrate:fresh --seed --env=testing --force
  php artisan dusk:chrome-driver --detect --no-interaction
"

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
  php artisan dusk tests/BrowserQA/ --env=testing --log-junit tests/ci-reports/junit.xml
" || DUSK_EXIT=$?

echo "==> Generating HTML report..."
python3 "${ROOT_DIR}/scripts/ci/generate-dusk-html-report.py" \
  --junit "${ROOT_DIR}/${JUNIT_PATH}" \
  --output "${ROOT_DIR}/${HTML_PATH}" || true

if [[ ! -f "${ROOT_DIR}/${HTML_PATH}" ]]; then
  echo "WARN: HTML report missing at ${HTML_PATH}" >&2
fi

echo "==> Dusk finished with exit code ${DUSK_EXIT}"
exit "${DUSK_EXIT}"
