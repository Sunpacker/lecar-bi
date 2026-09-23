# Local infrastructure

Только базовые зависимости первой версии: PostgreSQL и Redis. Backend и frontend имеют собственные Dockerfile и разворачиваются независимо.

- `docker-compose.yml` описывает production-like запуск immutable образов.
- `docker-compose.dev.yml` добавляет development target для frontend, bind mount исходников и отдельные тома для зависимостей и `.next`.

Из корня репозитория используйте `make dev` для разработки с Fast Refresh и `make infra-up` для проверки production-сборки.

Development entrypoint синхронизирует `node_modules` с `package-lock.json` только после изменения lock-файла, поэтому именованный Docker volume не сохраняет устаревшие зависимости.

После запуска проверить связь сервисов можно командой:

```bash
make integration
```

## VPS: публикация backend через Caddy

`docker-compose.vps.yml` запускает только Laravel backend, PostgreSQL и Redis. Backend не публикует порт хоста: Caddy обращается к нему по имени `lecar-bi-backend:8080` через существующую внешнюю Docker-сеть `proxy`. Имя сети можно изменить через `CADDY_NETWORK` в `infra/.env`.

Конфигурация Caddy для `api.veloza.ru`:

```caddyfile
handle_path /lecar-bi/* {
    reverse_proxy lecar-bi-backend:8080
}
```

`handle_path` удаляет префикс `/lecar-bi`, поэтому Laravel получает исходные маршруты `/api/v1/*`.

Запускать из корня репозитория:

```bash
docker compose --env-file infra/.env -f infra/docker-compose.vps.yml up --build -d
docker compose --env-file infra/.env -f infra/docker-compose.vps.yml exec backend php artisan migrate --force
curl --fail --show-error https://api.veloza.ru/lecar-bi/api/v1/health
```

`infra/.env` не хранится в Git. Перед первым запуском его нужно безопасно передать на VPS; `APP_KEY` и `POSTGRES_PASSWORD` должны оставаться секретными.
