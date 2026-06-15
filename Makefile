DC=docker compose
PHP_SHELL=$(DC) exec -T app bash -lc
ARTISAN=$(DC) exec -T app php /var/www/html/app/artisan
COMPOSER_CMD=$(PHP_SHELL) 'cd /var/www/html/app && composer'

.PHONY: build up down restart logs sh install composer-install composer-update composer-require migrate seed seed-class test coverage dusk cs-fix analyse fresh qa qa-smoke qa-admin qa-admin-core qa-admin-billing qa-admin-sessions qa-therapist qa-student qa-finance qa-e2e qa-browser qa-browser-report qa-quick qa-full qa-debug qa-fresh assets-build cache-clear send-reminders queue-work npm-install allure-install qa-allure-report

build:
	$(DC) build

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) down && $(DC) up -d

logs:
	$(DC) logs -f

sh:
	$(DC) exec app bash -lc "cd /var/www/html/app && bash || sh"

composer-install:
	$(PHP_SHELL) 'cd /var/www/html/app && composer install --no-interaction'

composer-update:
	$(PHP_SHELL) 'cd /var/www/html/app && composer update --no-interaction'

composer-require:
	@test -n "$(PACKAGE)" || (echo "Usage: make composer-require PACKAGE=sentry/sentry-laravel" && exit 1)
	$(COMPOSER_CMD) require $(PACKAGE) --no-interaction

composer-require-dev:
	@test -n "$(PACKAGE)" || (echo "Usage: make composer-require-dev PACKAGE=laravel/pint" && exit 1)
	$(COMPOSER_CMD) require --dev $(PACKAGE) --no-interaction

install:
	$(COMPOSER_CMD) install --no-interaction
	$(PHP_SHELL) 'cd /var/www/html/app && npm install'

init-env:
	$(PHP_SHELL) 'cp -n /var/www/html/docker/env/app.env /var/www/html/app/.env || true'
	$(ARTISAN) key:generate --force

init-test-env:
	$(PHP_SHELL) 'cp -n /var/www/html/docker/env/testing.env /var/www/html/app/.env.testing || true'
	$(PHP_SHELL) 'cd /var/www/html/app && if [ -f .env.dusk.local ]; then if ! grep -q "^APP_KEY=" .env.dusk.local; then APP_KEY=$$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2); if [ -n "$$APP_KEY" ]; then echo "APP_KEY=$$APP_KEY" >> .env.dusk.local; fi; fi; fi'

migrate:
	$(ARTISAN) migrate

seed:
	$(ARTISAN) db:seed

seed-class:
	@test -n "$(SEEDER)" || (echo "Usage: make seed-class SEEDER=Database\\Seeders\\MySeeder" && exit 1)
	$(ARTISAN) db:seed --class="$(SEEDER)"

fresh:
	$(ARTISAN) migrate:fresh

test:
	$(ARTISAN) config:clear
	$(ARTISAN) cache:clear
	$(PHP_SHELL) 'cd /var/www/html/app && export APP_ENV=testing DB_DATABASE=bird_test DB_HOST=mysql DB_PORT=3306 && if [ -f ./vendor/bin/pest ]; then ./vendor/bin/pest; elif [ -f ./vendor/bin/phpunit ]; then ./vendor/bin/phpunit; elif php artisan list --raw | grep -q "^test"; then php artisan test; else echo "No test runner available. Install dev dependencies." && exit 1; fi'

coverage:
	$(PHP_SHELL) 'cd /var/www/html/app && export APP_ENV=testing DB_DATABASE=bird_test DB_HOST=mysql DB_PORT=3306 && if [ -f ./vendor/bin/pest ]; then XDEBUG_MODE=coverage ./vendor/bin/pest --coverage; elif [ -f ./vendor/bin/phpunit ]; then XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage; elif php artisan list --raw | grep -q "^test"; then XDEBUG_MODE=coverage php artisan test --coverage; else echo "No test runner available. Install dev dependencies." && exit 1; fi'

dusk:
	$(PHP_SHELL) 'cd /var/www/html/app && php artisan dusk --env=local $(DUSK_ARGS)'

cs-fix:
	$(PHP_SHELL) 'cd /var/www/html/app && vendor/bin/pint'

analyse:
	$(PHP_SHELL) 'cd /var/www/html/app && vendor/bin/phpstan analyse --memory-limit=512M'

qa:
	$(DC) exec -T app bash -lc 'cd /var/www/html/app && vendor/bin/pint --test'
	$(DC) exec -T app bash -lc 'cd /var/www/html/app && vendor/bin/phpstan analyse --no-progress --memory-limit=512M'
	$(DC) exec -T app bash -lc 'cd /var/www/html/app && export APP_ENV=testing DB_DATABASE=bird_test DB_HOST=mysql DB_PORT=3306 && if [ -f ./vendor/bin/pest ]; then XDEBUG_MODE=coverage php -d memory_limit=512M ./vendor/bin/pest --min=80; elif [ -f ./vendor/bin/phpunit ]; then XDEBUG_MODE=coverage php -d memory_limit=512M ./vendor/bin/phpunit --testsuite=Feature; elif php artisan list --raw | grep -q "^test"; then XDEBUG_MODE=coverage php -d memory_limit=512M artisan test --min=80; else echo "No test runner available. Install dev dependencies." && exit 1; fi'

qa-smoke:
	@echo "🚀 Running smoke tests (3 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Smoke/ --env=testing

qa-admin:
	@echo "🚀 Running admin tests (15 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Admin/ --env=testing

qa-admin-core:
	@echo "🚀 Running admin core tests (2 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Admin/QaAdminCoreBrowserTest.php --env=testing

qa-admin-billing:
	@echo "🚀 Running admin billing tests (1 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Admin/QaAdminBillingBrowserTest.php --env=testing

qa-admin-sessions:
	@echo "🚀 Running admin sessions tests (2 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Admin/QaAdminSessionsBrowserTest.php --env=testing

qa-therapist:
	@echo "🚀 Running therapist tests (10 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Therapist/ --env=testing

qa-student:
	@echo "🚀 Running student tests (8 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Student/ --env=testing

qa-finance:
	@echo "🚀 Running finance tests (8 min)..."
	$(ARTISAN) dusk tests/BrowserQA/Finance/ --env=testing

qa-e2e:
	@echo "🚀 Running end-to-end tests (15 min)..."
	$(ARTISAN) dusk tests/BrowserQA/E2E/ --env=testing

qa-browser:
	@echo "🚀 Running all browser tests (45 min)..."
	$(ARTISAN) dusk tests/BrowserQA/ --env=testing

qa-browser-report:
	@echo "🚀 Running all browser tests with report..."
	bash scripts/ci/run-browser-qa.sh

qa-quick:
	@echo "🚀 Quick QA check (smoke + unit tests)..."
	$(ARTISAN) dusk tests/BrowserQA/Smoke/ --env=testing
	make test

qa-full:
	@echo "🚀 Running full QA pipeline..."
	$(DC) exec -T app bash -lc 'cd /var/www/html/app && vendor/bin/pint --test'
	$(DC) exec -T app bash -lc 'cd /var/www/html/app && vendor/bin/phpstan analyse --no-progress --memory-limit=512M'
	$(ARTISAN) test --env=testing
	$(ARTISAN) dusk tests/BrowserQA/ --env=testing
	@echo "✓ Full QA complete!"

qa-debug:
	@echo "🚀 Running tests in debug mode..."
	$(ARTISAN) dusk tests/BrowserQA/ --verbose --env=testing

qa-fresh:
	@echo "🚀 Fresh database + tests..."
	$(ARTISAN) migrate:fresh --seed --env=testing --force
	$(ARTISAN) dusk tests/BrowserQA/ --env=testing

npm-install:
	$(PHP_SHELL) 'cd /var/www/html/app && npm install'

allure-install:
	$(PHP_SHELL) 'cd /var/www/html/app && npm install --save-dev allure-commandline'

qa-allure-report:
	@echo "📊 View Allure reports:"
	@echo "   file://$(PWD)/app/tests/allure-report/index.html"
	@if [ -d "$(PWD)/app/tests/allure-report" ]; then \
		echo "✅ Report ready"; \
	else \
		echo "⚠️  No report yet. Run: bash scripts/qa/run-qa-report.sh <suite> <path>"; \
	fi

init: build up install init-env migrate

assets-build:
	$(PHP_SHELL) 'cd /var/www/html/app && npm install'
	$(PHP_SHELL) 'cd /var/www/html/app && npm run build'

cache-clear:
	$(ARTISAN) optimize:clear

send-reminders:
	$(ARTISAN) schedule:send-reminders

queue-work:
	$(ARTISAN) queue:work
