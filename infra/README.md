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

Из корня репозитория используйте `make dev` для разработки с Fast Refresh и `make infra-up` для проверки production-сборки.

Development entrypoint синхронизирует `node_modules` с `package-lock.json` только после изменения lock-файла, поэтому именованный Docker volume не сохраняет устаревшие зависимости.

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

`infra/.env` не хранится в Git. Перед первым запуском его нужно безопасно передать на VPS; `APP_KEY`, `POSTGRES_PASSWORD`, `NOTIFICATION_APP_KEY` и `NOTIFICATION_POSTGRES_PASSWORD` должны оставаться секретными.

Шаблон для боевой конфигурации находится в `infra/.env.production.example`. При `APP_ENV=production` встроенный `ProductionSafetyCheck` автоматически блокирует запуск с `APP_DEBUG=true` или дефолтными ключами/паролями.

## Резервное копирование и Disaster Recovery

Система включает скрипты для резервного копирования и тестового восстановления обеих баз данных:

```bash
# Создание резервной копии обеих БД (сжатие gzip, хранение 7 дней)
./scripts/backup-db.sh all

# Восстановление БД из резервной копии
./scripts/restore-db.sh backups/analytics_YYYYMMDD_HHMMSS.sql.gz postgres autobi
./scripts/restore-db.sh backups/notification_YYYYMMDD_HHMMSS.sql.gz notification-postgres notification

# Автоматизированные учения по восстановлению (Disaster Recovery Drill)
./scripts/restore-drill.sh
```

Подробные регламенты:
- [Матрица угроз и ASVS 5.0.0](../docs/security/threat-model.md)
- [Регламент ликвидации аварий](../docs/runbooks/production-failure-recovery.md)
- [Политика резервного копирования и RPO/RTO](../docs/operations/backup-and-disaster-recovery.md)

