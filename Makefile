INFRA_ENV_FILE ?= $(if $(wildcard infra/.env),infra/.env,infra/.env.example)
COMPOSE = docker compose --env-file $(INFRA_ENV_FILE) -f infra/docker-compose.yml
DEV_COMPOSE = $(COMPOSE) -f infra/docker-compose.dev.yml

.PHONY: install install-frontend install-backend dev dev-down dev-frontend infra-up infra-down build check check-frontend check-backend check-contracts test integration migrate migrate-seed migrate-fresh seed artisan
install: install-frontend install-backend

install-frontend:
	npm --prefix frontend ci

install-backend:
	composer --working-dir=backend install --no-interaction

dev:
	$(DEV_COMPOSE) up --build -d

dev-down:
	$(DEV_COMPOSE) down

dev-frontend:
	npm --prefix frontend run dev

infra-up:
	$(COMPOSE) up --build -d

infra-down:
	$(COMPOSE) down

build:
	npm --prefix frontend run build
	docker compose --env-file $(INFRA_ENV_FILE) -f infra/docker-compose.yml build backend

check: check-contracts check-frontend check-backend

check-contracts:
	npm --prefix frontend run contracts:validate
	npm --prefix frontend run api:generate

check-frontend:
	npm --prefix frontend run lint
	npm --prefix frontend run format:check
	npm --prefix frontend run typecheck
	npm --prefix frontend test
	npm --prefix frontend run build

check-backend:
	composer --working-dir=backend validate --strict
	composer --working-dir=backend lint
	composer --working-dir=backend test

test:
	npm --prefix frontend test
	composer --working-dir=backend test

integration:
	scripts/verify-integration.sh

migrate:
	$(COMPOSE) exec backend php artisan migrate

migrate-seed:
	$(COMPOSE) exec backend php artisan migrate --seed

migrate-fresh:
	$(COMPOSE) exec backend php artisan migrate:fresh --seed

seed:
	$(COMPOSE) exec backend php artisan db:seed

artisan:
	$(COMPOSE) exec backend php artisan $(cmd)
