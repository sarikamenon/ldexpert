@echo off
REM Simple Windows Makefile wrapper

if "%1"=="" goto help
if "%1"=="qa-admin" goto qa_admin
if "%1"=="qa-admin-core" goto qa_admin_core
if "%1"=="qa-admin-billing" goto qa_admin_billing
if "%1"=="qa-admin-sessions" goto qa_admin_sessions
if "%1"=="qa-therapist" goto qa_therapist
if "%1"=="qa-e2e" goto qa_e2e
if "%1"=="qa-student" goto qa_student
if "%1"=="qa-finance" goto qa_finance
if "%1"=="qa-smoke" goto qa_smoke
if "%1"=="qa-browser" goto qa_browser
if "%1"=="qa-quick" goto qa_quick
if "%1"=="qa-debug" goto qa_debug
if "%1"=="qa-fresh" goto qa_fresh
if "%1"=="up" goto up
if "%1"=="down" goto down
if "%1"=="restart" goto restart
if "%1"=="build" goto build
if "%1"=="migrate" goto migrate
if "%1"=="fresh" goto fresh
if "%1"=="seed" goto seed
if "%1"=="test" goto test
if "%1"=="cache-clear" goto cache_clear
if "%1"=="assets-build" goto assets_build

echo Unknown command: %1
goto help

:qa_admin
echo.
echo Running all admin tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh admin tests/BrowserQA/Admin/
exit /b %ERRORLEVEL%

:qa_admin_core
echo.
echo Running admin core tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh admin-core tests/BrowserQA/Admin/QaAdminCoreBrowserTest.php
exit /b %ERRORLEVEL%

:qa_admin_billing
echo.
echo Running admin billing tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh admin-billing tests/BrowserQA/Admin/QaAdminBillingBrowserTest.php
exit /b %ERRORLEVEL%

:qa_admin_sessions
echo.
echo Running admin sessions tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh admin-sessions tests/BrowserQA/Admin/QaAdminSessionsBrowserTest.php
exit /b %ERRORLEVEL%

:qa_therapist
echo.
echo Running all therapist tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh therapist tests/BrowserQA/Therapist/
exit /b %ERRORLEVEL%

:qa_e2e
echo.
echo Running all E2E tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh e2e tests/BrowserQA/E2E/
exit /b %ERRORLEVEL%

:qa_student
echo.
echo Running student tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh student tests/BrowserQA/Student/
exit /b %ERRORLEVEL%

:qa_finance
echo.
echo Running finance tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh finance tests/BrowserQA/Finance/
exit /b %ERRORLEVEL%

:qa_smoke
echo.
echo Running smoke tests (with report generation)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh smoke tests/BrowserQA/Smoke/
exit /b %ERRORLEVEL%

:qa_browser
echo.
echo Running all browser tests (with report generation, ~45 min)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh all tests/BrowserQA/
exit /b %ERRORLEVEL%

:qa_quick
echo.
echo Quick QA check (smoke + report)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh smoke tests/BrowserQA/Smoke/
exit /b %ERRORLEVEL%

:qa_debug
echo.
echo Running all browser tests (verbose, with report)...
echo.
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh all tests/BrowserQA/
exit /b %ERRORLEVEL%

:qa_fresh
echo.
echo Fresh database then all browser tests (with report)...
echo.
docker compose exec -T app bash -lc "php artisan migrate:fresh --seed --env=testing --force"
docker compose exec -T app bash /var/www/html/scripts/qa/run-suite-docker.sh all tests/BrowserQA/
exit /b %ERRORLEVEL%

:up
docker compose up -d
exit /b %ERRORLEVEL%

:down
docker compose down
exit /b %ERRORLEVEL%

:restart
docker compose down
docker compose up -d
exit /b %ERRORLEVEL%

:build
docker compose build
exit /b %ERRORLEVEL%

:migrate
docker compose exec -T app php /var/www/html/app/artisan migrate
exit /b %ERRORLEVEL%

:fresh
docker compose exec -T app php /var/www/html/app/artisan migrate:fresh
exit /b %ERRORLEVEL%

:seed
docker compose exec -T app php /var/www/html/app/artisan db:seed
exit /b %ERRORLEVEL%

:test
docker compose exec -T app php /var/www/html/app/artisan test --env=testing
exit /b %ERRORLEVEL%

:cache_clear
docker compose exec -T app php /var/www/html/app/artisan optimize:clear
exit /b %ERRORLEVEL%

:assets_build
docker compose exec -T app bash -lc "cd /var/www/html/app && npm install && npm run build"
exit /b %ERRORLEVEL%

:help
echo.
echo ========== LD Expert Bird - Makefile Commands ==========
echo.
echo QA TESTS (Browser Tests with Dusk):
echo   make qa-admin       - All admin tests (42+ including 10 NEW)
echo   make qa-admin-core  - Admin core authentication/dashboard tests (2 min)
echo   make qa-admin-billing - Admin billing & invoices tests (1 min)
echo   make qa-admin-sessions - Admin session approval tests (2 min)
echo   make qa-therapist   - All therapist tests (188+ including 15 NEW)
echo   make qa-e2e         - All E2E tests (21+ including 5 NEW)
echo   make qa-student     - Student tests
echo   make qa-finance     - Finance tests
echo   make qa-smoke       - Quick smoke tests (3 min)
echo   make qa-browser     - All browser tests combined
echo   make qa-quick       - Quick QA check
echo   make qa-debug       - Debug mode
echo   make qa-fresh       - Fresh DB + tests
echo.
echo DOCKER:
echo   make up             - Start Docker
echo   make down           - Stop Docker
echo   make restart        - Restart Docker
echo   make build          - Build Docker images
echo.
echo DATABASE:
echo   make migrate        - Run migrations
echo   make fresh          - Reset database
echo   make seed           - Seed database
echo.
echo OTHER:
echo   make test           - Unit tests
echo   make cache-clear    - Clear cache
echo   make assets-build   - Build frontend
echo.
exit /b 0
