# Резервное копирование и аварийное восстановление (Backup & Disaster Recovery)

Документ подготовлен в рамках [Phase 18 — Production Hardening](../roadmap/18-production-hardening.md) и регламентирует политики сохранения, восстановления данных и развертывания системы AutoBI.

---

## 1. Целевые показатели непрерывности (RPO и RTO)

| Параметр | Целевое значение (SLA) | Фактическое подтверждение (Drill) | Механизм обеспечения |
| :--- | :--- | :--- | :--- |
| **RPO (Recovery Point Objective)** | **< 1 час** (с Outbox replay: **0 потерь**) | **0 записей** (полное соответствие) | Ежедневные/почасовые снимки `pg_dump` + Transactional Outbox в Analytics DB. При восстановлении снимка события старше бэкапа повторно воспроизводятся через `php artisan outbox:publish`. |
| **RTO (Recovery Time Objective)** | **< 15 минут** (900 секунд) | **12 секунд** | Автоматизированный скрипт `scripts/restore-db.sh` восстанавливает базу данных в транзакции за несколько секунд. |

---

## 2. Архитектура резервного копирования

Каждый сервис использует отдельное хранилище данных:
- **`postgres` (Analytics Service):** хранит справочники, факты продаж, снимки остатков, пользовательские дашборды, правила алертов и Outbox сообщений.
- **`notification-postgres` (Notification Service):** хранит сформированные уведомления и журнал дедупликации `consumed_events`.

Бэкапы обеих БД выполняются независимо скриптом `scripts/backup-db.sh`.
Файлы сохраняются в сжатом виде (`.sql.gz`) с правами `0600` и меткой времени:
```
backups/
├── analytics_20260925_091500.sql.gz
└── notification_20260925_091500.sql.gz
```

### Политика ротации (Retention Policy)
- По умолчанию бэкапы старше 7 дней автоматически удаляются локально (`RETENTION_DAYS=7`).
- Для production рекомендуется выгрузка зашифрованных копий в S3/offsite-хранилище (например, через AWS CLI / rclone).

---

## 3. Регламент аварийного восстановления (Restore Procedure)

### 3.1. Ручное восстановление
Для восстановления базы данных из бэкапа:
```bash
# Восстановление аналитической БД
./scripts/restore-db.sh backups/analytics_YYYYMMDD_HHMMSS.sql.gz postgres autobi

# Восстановление БД уведомлений
./scripts/restore-db.sh backups/notification_YYYYMMDD_HHMMSS.sql.gz notification-postgres notification
```

### 3.2. Согласованность Notification Service после восстановления
При восстановлении Notification Service:
1. Таблица `consumed_events` защищает от дублирования уже полученных событий.
2. Новые события с момента снятия бэкапа считываются воркером из Redis Stream `autobi.integration-events`.
3. Если Redis Stream был очищен, Analytics Service повторно публикует события из таблицы `outbox_messages` командой `php artisan outbox:retry --force && php artisan outbox:publish`.

---

## 4. Disaster Recovery Drill (Учения по восстановлению)

Процедура учений автоматизирована в скрипте:
```bash
./scripts/restore-drill.sh
```

**Протокол учений:**
1. Создание снимков обеих баз данных.
2. Создание изолированных баз данных `autobi_drill` и `notification_drill`.
3. Восстановление данных в изолированные инстансы.
4. Проверка контрольных сумм и количества строк (`fact_orders`, `workspaces`, `notifications`, `consumed_events`).
5. Фиксация фактического RTO и RPO.
6. Очистка временных структур.

---

## 5. Чистый запуск и стратегия деплоя

1. **Иммутабельные образы и sha256 digests:**
   В production образы собираются и деплоятся с явными тегами версий или хэш-суммами (`image: autobi/backend:sha-abc1234`), запрещено использование мутабельного `latest`.
2. **Секреты вне Git:**
   Файл `infra/.env` создается из шаблона `infra/.env.example` на целевом сервере. Переменные `APP_KEY`, `POSTGRES_PASSWORD`, `NOTIFICATION_APP_KEY`, `NOTIFICATION_POSTGRES_PASSWORD` обязательны к заполнению. Встроенный `ProductionSafetyCheck` блокирует старт при попытке запустить production с дефолтными секретами.
3. **Обратная совместимость миграций (Zero-Downtime Rollback):**
   - Все миграции должны быть расширяющими (additive): новые колонки создаются `nullable` или с дефолтными значениями.
   - Удаление или переименование полей выполняется в два этапа (через deprecated фазу).
   - В случае необходимости отката приложения (`rollback`), предыдущая версия кода гарантированно работает с обновленной схемой БД.
