# 10. Testing and Quality

## Общая стратегия

Тестирование должно соответствовать архитектурным слоям и давать быстрый feedback при изменении кода.

## Domain Tests

Domain Layer тестируется преимущественно unit-тестами.

Основные цели:

- проверка бизнес-правил;
- проверка invariant;
- проверка value objects;
- проверка domain services;
- проверка domain events.

Domain-тесты не должны требовать запуска Laravel или базы данных без необходимости.

## Application Tests

Application Layer тестирует use cases.

В таких тестах допустимо использовать:

- fakes;
- mocks;
- in-memory repository implementations;
- test doubles внешних интерфейсов.

Цель — проверить orchestration и корректное взаимодействие с Domain.

## Infrastructure Tests

Infrastructure Layer должен иметь integration tests.

Они проверяют:

- PostgreSQL;
- Eloquent mappings;
- repository implementations;
- Redis;
- очереди;
- integrations;
- миграции.

## Presentation Tests

HTTP API должен покрываться feature и API tests.

Проверяются:

- validation;
- status codes;
- сериализация;
- authorization;
- соответствие API-контракту.

## Frontend Tests

Frontend должен включать:

- unit-тесты для utilities;
- component tests;
- integration tests для сложных UI-сценариев;
- end-to-end тесты ключевых пользовательских потоков.

## Contract Tests

Необходимо проверять соответствие Laravel API опубликованному OpenAPI-контракту.

Изменение API должно обнаруживаться до production.

## RAG Support Chat

Обычный CI использует детерминированные AI doubles и проверяет orchestration, отсутствие chat-вызова
при `no_context`, отзыв источника, citations, reconnect и workspace isolation. PostgreSQL suite запускается
отдельно с `RUN_SUPPORT_POSTGRES_INTEGRATION=1` на одноразовой базе с pgvector и выполняет destructive
`migrate:fresh`; SQLite не заменяет эту проверку. Production выпуск дополнительно требует реального
proxy/BFF streaming test и versioned 60-question holdout на утверждённом provider/profile.

## Архитектурные тесты

Желательно автоматически контролировать ключевые ограничения:

- Domain не зависит от Infrastructure;
- bounded contexts не обходят публичные границы;
- запрещённые framework-зависимости не попадают в Domain;
- frontend не импортирует внутренности backend.

## Definition of Done

Функциональность не считается завершённой без:

- тестов нужного уровня;
- обновлённого API-контракта при изменении интерфейса;
- обновлённой документации при изменении архитектуры;
- миграций при изменении схемы данных;
- проверок CI.
