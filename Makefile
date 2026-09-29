.PHONY: up down build logs shell migrate

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
	docker compose exec app vendor/bin/doctrine-migrations migrate --no-interaction
