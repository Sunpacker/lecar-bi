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

VPS Compose принимает готовые `BACKEND_IMAGE` и `NOTIFICATION_IMAGE` из GHCR. Сборки на сервере и команды `make vps-up` больше нет. Frontend не входит в этот Compose и обновляется отдельно.

### Первоначальное подключение

1. Создать отдельного пользователя `deploy` с SSH-ключом и правом запускать Docker Compose v2. Добавить его публичный ключ в `authorized_keys`, закрытый ключ — в GitHub secret `VPS_SSH_KEY`. Закрепить проверенный SSH host key в secret `VPS_KNOWN_HOSTS`; не получать его без проверки непосредственно в workflow. Пользователь должен иметь доступ к Docker socket и право записи в `/opt/lecar-bi/releases`, `/opt/lecar-bi/incoming`, `/opt/lecar-bi/backups`, `/opt/lecar-bi/deploy-state`. Доступ к Docker socket эквивалентен root-доступу; выдавать его только доверенной учётной записи.
2. На VPS хранить `infra/.env` вне `releases/`, например `/opt/lecar-bi/infra/.env`, с правами `0600` для владельца `deploy` или `0640` для группы `deploy`. Заполнить шаблон `infra/.env.production.example`, включая `GRAFANA_ADMIN_PASSWORD`, ключи приложений и пароли обеих БД. Образы в файле служат примером; при деплое скрипт подставляет образы точного SHA. Docker Compose project должен оставаться `lecar-bi`, внешняя сеть `proxy` должна существовать.
3. Для приватных GHCR packages выполнить `docker login ghcr.io` под пользователем деплоя с отдельным токеном `read:packages`. Токен остаётся только на VPS; GitHub Actions публикует образы своим краткоживущим `GITHUB_TOKEN`. Проверить `docker pull` обоих образов вручную.
4. Настроить GitHub repository variables: `VPS_HOST`, `VPS_PORT`, `VPS_USER=deploy`, `VPS_PATH=/opt/lecar-bi`, `VPS_PROJECT=lecar-bi`, `VPS_ENV_FILE=/opt/lecar-bi/infra/.env`, `VPS_BACKUP_DIR=/opt/lecar-bi/backups`. SSH-порт задаётся явно. Секреты: `VPS_SSH_KEY`, `VPS_KNOWN_HOSTS`.
5. До включения workflow сверить `docker compose ls`, `docker ps` и `docker volume ls`: существующие контейнеры и тома должны относиться к `lecar-bi`. Подготовить `notification`, `notification-worker`, вторую БД и мониторинг, если они ещё не установлены. Первый автоматический деплой требует работающие три приложения и все шесть именованных томов. Сохранить image ID исходных контейнеров и два проверенных бэкапа вне каталога релизов. Не переименовывать существующие тома и не выполнять `docker compose down --volumes`.

После успешного job `integration` workflow публикует `backend` и `notification` с тегом полного SHA, проверяет актуальность `main`, передаёт пакет в `VPS_PATH/releases/<SHA>` и запускает `scripts/deploy-vps.sh`. Пакет включает Compose и всё дерево `infra/observability`, которое требуется относительным bind mounts. Скрипт держит `flock`, сверяет проект и тома, скачивает образы, сохраняет digest, создаёт бэкапы обеих БД и только затем останавливает три приложения. PostgreSQL, Redis и мониторинг продолжают работать. После миграций он проверяет health, публичный API, readiness уведомлений, свежий heartbeat worker и отсутствие рестартов. Повторный запуск успешного SHA проверяет сервисы без повторных миграций.

При ошибке после остановки скрипт возвращает прежние image ID приложений. Схема БД остаётся мигрированной, поэтому миграции должны быть совместимы с предыдущей версией. Если прежние образы недоступны или восстановление не прошло, оператор использует сохранённые бэкапы и [регламент восстановления](../docs/operations/backup-and-disaster-recovery.md). Обычная очистка Docker-образов не должна удалять бэкапы или image ID текущего и предыдущего релиза.

Для ручного возврата только приложений взять digest из `/opt/lecar-bi/deploy-state/previous` (для первого релиза — image ID из `deploy-state/baseline`) и запустить три сервиса без миграций:

```bash
state=/opt/lecar-bi/deploy-state/previous
BACKEND_IMAGE=$(sed -n 's/^backend_image=//p' "$state")
NOTIFICATION_IMAGE=$(sed -n 's/^notification_image=//p' "$state")
export BACKEND_IMAGE NOTIFICATION_IMAGE
docker compose --env-file /opt/lecar-bi/infra/.env -f /opt/lecar-bi/releases/<SHA>/infra/docker-compose.vps.yml -p lecar-bi up -d --no-deps --force-recreate backend notification notification-worker
```

После ручного возврата проверить health API и worker; при несовместимой схеме перейти к восстановлению БД по регламенту.

Для ручного восстановления БД сначала остановить приложения и выбрать проверенный бэкап. Команда требует явные Compose project и server env; восстановление выполняется оператором по регламенту, не запускается при автоматическом откате:

```bash
/opt/lecar-bi/releases/<SHA>/scripts/restore-db.sh /opt/lecar-bi/backups/analytics_<TIMESTAMP>.sql.gz postgres --compose-file /opt/lecar-bi/releases/<SHA>/infra/docker-compose.vps.yml --project lecar-bi --env-file /opt/lecar-bi/infra/.env
/opt/lecar-bi/releases/<SHA>/scripts/restore-db.sh /opt/lecar-bi/backups/notification_<TIMESTAMP>.sql.gz notification-postgres --compose-file /opt/lecar-bi/releases/<SHA>/infra/docker-compose.vps.yml --project lecar-bi --env-file /opt/lecar-bi/infra/.env
```

Ручная проверка после релиза:

```bash
curl --fail --show-error https://api.veloza.ru/lecar-bi/api/v1/health
docker compose --env-file /opt/lecar-bi/infra/.env -f /opt/lecar-bi/releases/<SHA>/infra/docker-compose.vps.yml -p lecar-bi ps
```

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
