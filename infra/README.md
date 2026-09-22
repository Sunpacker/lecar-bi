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
