# AutoBI

Проект разделён на два самостоятельных deployable-сервиса:

- `frontend` — отдельное Next.js-приложение на React/TypeScript.
- `backend` — отдельный Laravel analytics-микросервис на PHP.

Frontend и backend не разделяют исходный код и не импортируют внутренности друг друга. Взаимодействие выполняется только через OpenAPI HTTP API и будущие integration events.

## Структура

- `frontend/` — только Next.js, React, TypeScript и frontend-конфигурация.
- `backend/` — только Laravel/PHP, DDD bounded contexts и backend-конфигурация.
- `contracts/openapi/` — публичный API-контракт.
- `infra/docker-compose.yml` — orchestration frontend, backend, PostgreSQL и Redis.
- `docs/` — архитектурные правила и локальный запуск.

## Режим разработки

Установить воспроизводимые зависимости обоих сервисов:

```bash
make install
```

```bash
make dev
```

Команда запускает весь стек в Docker, а frontend — через `next dev`. Каталог `frontend/` примонтирован в контейнер, поэтому изменения компонентов появляются в браузере автоматически без пересборки образа.

Остановить dev-среду:

```bash
make dev-down
```

Без локального env-файла команды используют настройки из `infra/.env.example`. Для переопределения параметров создайте локальный файл перед запуском:

```bash
cp infra/.env.example infra/.env
```

С настройками по умолчанию сервисы будут доступны по адресам:

- Frontend: `http://localhost:3000`
- Backend: `http://localhost:8080`
- Backend health: `http://localhost:8080/api/v1/health`

## Проверки качества

```bash
make check
```

Команда валидирует OpenAPI, обновляет generated TypeScript schema, запускает frontend lint, format check, typecheck, tests и production build, затем проверяет Composer metadata, форматирование, статический анализ, архитектурные ограничения и backend tests.

Для проверки уже запущенного полного стека:

```bash
make integration
```

## Проверка production-образов локально

```bash
make infra-up
```

Этот режим собирает immutable production-образы. Изменения исходников становятся видны только после повторной сборки, поэтому для ежедневной разработки используйте `make dev`.

## Независимый запуск

Frontend:

```bash
npm --prefix frontend ci
make dev-frontend
```

Backend:

```bash
cd backend
composer install
cp .env.example .env
php artisan serve --host=0.0.0.0 --port=8080
```
