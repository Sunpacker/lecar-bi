INFRA_ENV_FILE ?= $(if $(wildcard infra/.env),infra/.env,infra/.env.example)
DEV_ENV_FILE ?= infra/.env.dev
COMPOSE = docker compose --env-file $(INFRA_ENV_FILE) -f infra/docker-compose.yml
DEV_COMPOSE = docker compose --env-file $(DEV_ENV_FILE) -f infra/docker-compose.yml -f infra/docker-compose.dev.yml
VPS_COMPOSE = docker compose --env-file $(INFRA_ENV_FILE) -f infra/docker-compose.vps.yml
export COMPOSER_HOME ?= $(CURDIR)/.composer

.PHONY: install install-frontend install-backend install-notification prepare-dev-env dev dev-down dev-frontend infra-up infra-down build build-backend build-notification build-vps vps-up check check-frontend check-backend check-notification check-contracts test integration migrate migrate-seed migrate-fresh seed artisan notification-migrate notification-artisan performance-seed performance-benchmark
install: install-frontend install-backend install-notification

install-frontend:
	npm --prefix frontend ci

install-backend:
	composer --working-dir=backend install --no-interaction

install-notification:
	composer --working-dir=notification install --no-interaction

prepare-dev-env:
	python3 scripts/prepare-dev-env.py

dev: prepare-dev-env
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
	$(COMPOSE) build backend notification

build-backend:
	$(COMPOSE) build backend

build-notification:
	$(COMPOSE) build notification

build-vps:
	$(VPS_COMPOSE) build backend notification

vps-up:
	$(VPS_COMPOSE) up --build --detach --wait
	$(VPS_COMPOSE) exec -T backend php artisan migrate --force
	$(VPS_COMPOSE) exec -T notification php artisan migrate --force

check: check-contracts check-frontend check-backend check-notification

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

check-notification:
	composer --working-dir=notification validate --strict
	composer --working-dir=notification lint
	composer --working-dir=notification test

test:
	npm --prefix frontend test
	composer --working-dir=backend test
	composer --working-dir=notification test

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

notification-migrate:
	$(COMPOSE) exec notification php artisan migrate

notification-artisan:
	$(COMPOSE) exec notification php artisan $(cmd)

performance-seed:
	$(COMPOSE) exec backend php artisan performance:seed --profile=$(or $(profile),large) --workspace=$(or $(workspace),perf-ws-1) --seed=$(or $(seed),42)

performance-benchmark:
	$(COMPOSE) exec backend php artisan performance:benchmark --profile=$(or $(profile),large) --workspace=$(or $(workspace),perf-ws-1) --runs=$(or $(runs),30) --warmup=$(or $(warmup),5) --cache-state=$(or $(cache-state),disabled) --format=$(or $(format),table) $(if $(scenario),--scenario=$(scenario)) $(if $(output),--output=$(output)) $(if $(filter true 1,$(explain)),--explain)
