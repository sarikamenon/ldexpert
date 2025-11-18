DC=docker compose
PHP_SHELL=$(DC) exec -T app bash -lc
ARTISAN=$(PHP_SHELL) "cd app && php artisan"
COMPOSER_CMD=$(PHP_SHELL) 'cd app && composer'

.PHONY: build up down restart logs sh install migrate seed test coverage dusk cs-fix analyse fresh qa assets-build

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
	$(DC) exec app bash -lc "bash || sh"

install:
	$(PHP_SHELL) 'cd app && composer install --no-interaction'
	$(PHP_SHELL) 'cd app && npm install'

init-env:
	$(PHP_SHELL) 'cp -n docker/env/app.env app/.env || true'
	$(ARTISAN) key:generate --force

init-test-env:
	$(PHP_SHELL) 'cp -n docker/env/testing.env app/.env.testing || true'
	$(PHP_SHELL) 'cd app && if [ -f .env.dusk.local ]; then if ! grep -q "^APP_KEY=" .env.dusk.local; then APP_KEY=$$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2); if [ -n "$$APP_KEY" ]; then echo "APP_KEY=$$APP_KEY" >> .env.dusk.local; fi; fi; fi'

migrate:
	$(PHP_SHELL) 'cd app && php artisan migrate'

seed:
	$(PHP_SHELL) 'cd app && php artisan db:seed'

fresh:
	$(PHP_SHELL) 'cd app && php artisan migrate:fresh --seed'

test:
	$(PHP_SHELL) 'cd app && php artisan test'

coverage:
	$(PHP_SHELL) 'cd app && php artisan test --coverage'

dusk:
	$(PHP_SHELL) 'cd app && php artisan migrate:fresh --seed'
	$(PHP_SHELL) 'cd app && php artisan dusk'

cs-fix:
	$(DC) exec -T app vendor/bin/pint

analyse:
	$(DC) exec -T app vendor/bin/phpstan analyse

qa:
	$(DC) exec -T app bash -lc 'cd app && vendor/bin/pint --test'
	$(DC) exec -T app bash -lc 'cd app && vendor/bin/phpstan analyse --no-progress'
	$(DC) exec -T app bash -lc 'cd app && XDEBUG_MODE=coverage php artisan test --min=80'

init: build up install init-env migrate

assets-build:
	$(PHP_SHELL) 'cd app && npm install'
	$(PHP_SHELL) 'cd app && npm run build'

