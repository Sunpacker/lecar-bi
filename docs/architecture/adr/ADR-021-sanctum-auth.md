# ADR-021: Переход на Laravel Sanctum для аутентификации API

## Статус

Принято (2026-09-25)

## Контекст

Backend аутентифицировал запросы через заголовок `X-User-Id`, который передавался из Next.js BFF. Этот подход имел критическую уязвимость: любой, кто мог отправить HTTP-запрос напрямую к backend, мог указать произвольный `X-User-Id` и получить доступ к любому аккаунту без проверки.

Frontend хранил сессию в подписанной HMAC cookie (`autobi_session`), но backend не участвовал в сессионном управлении: logout удалял cookie на стороне frontend, при этом «сессия» на backend не существовала — не было механизма отзыва.

## Решение

Использовать **Laravel Sanctum** (API tokens) для аутентификации:

1. **Login** возвращает одноразовый plainText token; frontend сохраняет его в HttpOnly cookie.
2. **Каждый запрос** к backend содержит `Authorization: Bearer {token}`.
3. **Sanctum middleware** (`auth:sanctum`) проверяет токен, извлекает пользователя и устанавливает `authenticated_user_id` в request attributes.
4. **Logout** отзывает текущий токен на стороне backend.
5. **Смена пароля** отзывает все токены и требует повторного входа.
6. **Срок действия** токена — 7 дней (Sanctum expiration).

### Миграционный путь

- API v2 с Bearer-аутентификацией сосуществует параллельно.
- V1 защищённые маршруты возвращают `410 Gone` — требуется повторный вход.
- Health endpoints v1 сохранены для обратной совместимости мониторинга.
- Frontend перенаправляет все API-запросы через BFF proxy (`/api/backend/[...path]`), который добавляет Bearer token из cookie.

### Токены

- `tokenable_id` — строка (совместимость с существующими string user IDs).
- Таблица `personal_access_tokens` — стандартная Sanctum, с адаптированным типом ID.

## Альтернативы

| Альтернатива | Причина отказа |
|---|---|
| JWT (self-contained) | Невозможность мгновенного отзыва без дополнительной инфраструктуры (blacklist) |
| Passport (OAuth2) | Избыточная сложность для single-application use case |
| Laravel Session (cookie-based) | Сложность интеграции с Next.js SSR; stateful sessions плохо масштабируются |
| Оставить X-User-Id | Критическая уязвимость — не вариант |

## Последствия

- Все существующие тесты требуют обновления: вместо `'X-User-Id' => 'user-1'` нужно создавать Sanctum-токен.
- Frontend workspace-gateway и другие API gateways не передают `userId` — авторизация автоматическая.
- Rate limiters используют `authenticated_user_id` из request attributes.
- Согласованный релиз frontend/backend обязателен.
