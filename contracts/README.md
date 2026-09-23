Папка `contracts/` нужна для хранения **публичных контрактов взаимодействия между frontend и backend**.

В текущей архитектуре она содержит:

```text
contracts/
├── openapi/
│   └── analytics-v1.yaml
└── events/
    ├── alert-triggered.v1.schema.json   # JSON Schema для integration event
    └── alert-triggered.v1.example.json  # Canonical example (используется в contract tests)
```

## Что такое контракт

Контракт описывает, как frontend может обращаться к backend:

- какие endpoint доступны;
- какие HTTP-методы используются;
- какие параметры принимает endpoint;
- формат request body;
- формат response;
- возможные HTTP-коды ошибок;
- типы данных;
- версию API.

Например:

```yaml
paths:
  /health:
    get:
      responses:
        "200":
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/HealthResponse"
```

Это означает, что backend предоставляет endpoint:

```text
GET /api/v1/health
```

с заранее определённым форматом ответа.

## Зачем это frontend

Frontend не должен знать внутреннюю структуру Laravel:

```text
backend/app/Modules/...
backend/app/Domain/...
backend/app/Models/...
```

Он работает только с публичным API-контрактом:

```text
frontend → OpenAPI API → backend
```

Из OpenAPI можно автоматически сгенерировать TypeScript-клиент:

```text
contracts/openapi/analytics-v1.yaml
                     ↓
frontend/src/shared/api/generated/
```

Тогда frontend получает типизированные методы и модели, например:

```ts
api.getHealth();
api.getSalesSummary(params);
api.getInventoryReport(params);
```

Без ручного дублирования типов.

Проверить контракт и обновить generated schema можно из корня репозитория:

```bash
npm --prefix frontend run contracts:validate
npm --prefix frontend run api:generate
```

CI повторно генерирует schema и завершится с ошибкой, если результат не зафиксирован в репозитории.

## Зачем это backend

Backend использует контракт как формальное описание публичного API. Это помогает:

- поддерживать стабильную структуру endpoint;
- проверять request и response;
- делать contract tests;
- обнаруживать breaking changes;
- документировать API;
- независимо разворачивать frontend и backend.

## Почему папка находится отдельно

`contracts/` не является третьим микросервисом. Это общий технический слой монорепозитория.

```text
frontend/      # Next.js
backend/       # Laravel
contracts/     # API и event contracts
infra/         # Docker Compose, PostgreSQL, Redis
```

Она не должна содержать бизнес-логику, Laravel-классы или React-компоненты.

## Что ещё может находиться внутри

В будущем:

```text
contracts/
├── openapi/
│   └── analytics-v1.yaml
├── events/
│   ├── dataset-imported.v1.json
│   ├── projection-built.v1.json
│   └── alert-triggered.v1.json
└── README.md
```

- `openapi/` — синхронные HTTP-контракты;
- `events/` — контракты асинхронных интеграционных событий.

Итого: **`contracts/` — это единый источник правды для взаимодействия между двумя независимыми микросервисами**.
