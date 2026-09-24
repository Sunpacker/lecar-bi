# Local infrastructure

Инфраструктура состоит из независимых микросервисов и хранилищ:
- `frontend` — Next.js SPA/BFF
- `backend` — Laravel Analytics Service
- `postgres` — PostgreSQL для аналитического сервиса
- `notification` — Laravel Notification Service (HTTP API, health probes)
- `notification-worker` — долгоживущий демон обработки событий из Redis Streams
- `notification-postgres` — изолированный PostgreSQL для сервиса уведомлений (дедупликация и проекции)
- `redis` — брокер интеграционных событий (Redis Streams) и технический кэш

Сервисы имеют собственные Dockerfile и разворачиваются независимо. Базы данных строго изолированы: notification service не имеет доступа к аналитической БД.

- `docker-compose.yml` описывает production-like запуск immutable образов.
- `docker-compose.dev.yml` добавляет development target для frontend, bind mount исходников для backend и notification, и отдельные тома.

Dev backend, scheduler и AI workers получают одинаковые bind mounts приложения, OpenAPI-контрактов
и документации. `DEV_UID`/`DEV_GID` позволяют запускать их от UID/GID локального разработчика;
HTTP backend использует `artisan serve --no-reload`, чтобы reload-процесс не отбрасывал Compose env.

Локальный Google provider настраивается в `backend/.env`: сохраните там `GEMMA_API_KEY`, а
`SUPPORT_AI_ADAPTER=google` задайте в том же файле или экспортируйте перед `make dev`. Dev override
не подставляет пустой ключ поверх смонтированного `backend/.env`; backend и оба AI worker читают
одинаковую конфигурацию. Не переносите локальный ключ в `infra/.env`: этот файл используется для
параметров Compose и production-like/VPS запуска.

Из корня репозитория используйте `make dev` для разработки с Fast Refresh и `make infra-up` для проверки production-сборки.

Development entrypoint синхронизирует `node_modules` с `package-lock.json` только после изменения lock-файла, поэтому именованный Docker volume не сохраняет устаревшие зависимости.

Разделение dev/VPS provider config проверяется без обращения к Google API:

```bash
./scripts/verify-support-provider-compose.sh
```

После запуска проверить связь сервисов можно командой:

```bash
make integration
```

## VPS: публикация сервисов через Caddy

`docker-compose.vps.yml` запускает analytics backend, notification service, worker, redis и изолированные экземпляры PostgreSQL. Backend не публикует порт хоста: Caddy обращается к нему по имени `lecar-bi-backend:8080` через существующую внешнюю Docker-сеть `proxy`. Имя сети можно изменить через `CADDY_NETWORK` в `infra/.env`.

Конфигурация Caddy для `api.veloza.ru`:

```caddyfile
handle_path /lecar-bi/* {
    reverse_proxy lecar-bi-backend:8080
}
```

`handle_path` удаляет префикс `/lecar-bi`, поэтому Laravel получает исходные маршруты `/api/v1/*`.

Запускать из корня репозитория:

```bash
make vps-up
curl --fail --show-error https://api.veloza.ru/lecar-bi/api/v1/health
```

`make vps-up` собирает и запускает сервисы, ждёт успешных health checks и применяет production-миграции для analytics и notification баз данных.

`infra/.env` не хранится в Git. Перед первым запуском его нужно безопасно передать на VPS;
`APP_KEY`, `POSTGRES_PASSWORD`, `NOTIFICATION_APP_KEY`, `NOTIFICATION_POSTGRES_PASSWORD`,
`SESSION_SECRET` и `SUPPORT_BFF_SHARED_SECRET` должны оставаться секретными. Next.js deployment и
analytics backend получают одинаковый `SUPPORT_BFF_SHARED_SECRET`; браузерные переменные его не содержат.
На VPS `GEMMA_API_KEY` передаётся через `infra/.env` только analytics backend и AI workers. Для
Google provider задайте там `SUPPORT_AI_ADAPTER=google`; chat/embedding profiles pinned переменными
из `.env.example` и не экспортируются в frontend.
Оба секрета содержат не менее 32 случайных символов.
