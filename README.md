# AutoBI

Проект разделён на три самостоятельных deployable-сервиса:

- `frontend` — отдельное Next.js-приложение на React/TypeScript.
- `backend` — отдельный Laravel analytics-микросервис на PHP.
- `notification` — отдельный Laravel notification-микросервис на PHP для обработки интеграционных событий.

Сервисы не разделяют исходный код, хранилища и не импортируют внутренности друг друга. Взаимодействие выполняется через OpenAPI HTTP API и асинхронные Integration Events через Redis Streams.

## Структура

- `frontend/` — только Next.js, React, TypeScript и frontend-конфигурация.
- `backend/` — только Laravel/PHP, DDD bounded contexts и аналитическая база данных.
- `notification/` — только Laravel/PHP, обработчики событий Redis Streams и изолированная БД уведомлений.
- `contracts/` — публичный OpenAPI-контракт и схемы интеграционных событий (JSON Schema).
- `infra/docker-compose.yml` — orchestration frontend, backend, notification, PostgreSQL (analytics), notification-postgres и Redis.
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
- Analytics Backend: `http://localhost:8080`
- Analytics health: `http://localhost:8080/api/v1/health`
- Notification Service: `http://localhost:8081`
- Notification health (liveness/readiness): `http://localhost:8081/api/v1/health/live`, `http://localhost:8081/api/v1/health/ready`

## Проверки качества

```bash
make check
```

Команда валидирует OpenAPI, обновляет generated TypeScript schema, запускает frontend lint, format check, typecheck, tests и production build, проверяет Composer metadata, форматирование, статический анализ, архитектурные ограничения и тесты backend, а затем аналогичные проверки качества (composer validate, Pint, PHPStan, PHPUnit) для сервиса notification.

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

Analytics Backend:

```bash
cd backend
composer install
cp .env.example .env
php artisan serve --host=0.0.0.0 --port=8080
```

Notification Service:

```bash
cd notification
composer install
cp .env.example .env
php artisan serve --host=0.0.0.0 --port=8081
php artisan notifications:consume
```
