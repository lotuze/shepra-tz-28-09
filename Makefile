.PHONY: up down build logs shell migrate fixtures schema-validate test phpstan cs-check cs-fix openapi-validate e2e-install e2e worker-logs worker-restart

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

migrate:
	docker compose exec app php bin/console migrations:migrate --no-interaction

fixtures:
	docker compose exec app php bin/console fixtures:load --no-interaction

schema-validate:
	docker compose exec app php bin/console orm:validate-schema

test:
	docker compose exec -e APP_ENV=test app composer test

phpstan:
	docker compose exec app composer phpstan

cs-check:
	docker compose exec app composer cs-check

cs-fix:
	docker compose exec app composer cs-fix

openapi-validate:
	docker compose exec -e OPENAPI_SPEC=/docs/openapi.yaml -e OPENAPI_CONFIG=/docs/redocly.yaml frontend npm run openapi:validate

e2e-install:
	cd frontend && npx playwright install chromium

e2e:
	cd frontend && npm run e2e

worker-logs:
	docker compose logs -f worker

worker-restart:
	docker compose restart worker
