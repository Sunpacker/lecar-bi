# Notification Service

Сервис уведомлений AutoBI. Выделен в Phase 14 как независимый deployable микросервис.

## Ответственность

- Читает события `alert.triggered.v1` из Redis Stream (`autobi.integration-events`) через consumer group `notification-service-v1`.
- Идемпотентно дедуплицирует входящие сообщения (`consumed_events` по уникальному `event_id`).
- Сохраняет проекции уведомлений в собственной базе данных PostgreSQL (`notifications`).
- Предоставляет технические health/readiness endpoints:
  - `GET /api/v1/health/live`
  - `GET /api/v1/health/ready`
- Отправляет невалидные сообщения в dead-letter stream `autobi.integration-events.dead-letter`.

## Изоляция

- Сервис владеет собственной базой данных `notification-postgres`.
- Нет прямого сетевого доступа или учетных данных к analytics PostgreSQL.
- Нет синхронных HTTP-вызовов к сервису аналитики для обогащения данных.
