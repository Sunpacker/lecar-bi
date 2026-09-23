# Phase 18 — Production Hardening

## Кратко

Довести AutoBI до воспроизводимой и проверяемой portfolio-quality production demonstration: закрыть blocking security issues, защитить границу `browser → Next.js BFF → analytics`, ввести автоматическую проверку совместимости контрактов и миграций, ограничить ресурсы и повторные попытки, доказать backup/restore и отказоустойчивость, а затем собрать единый production-readiness checkpoint.

Phase 18 не добавляет новую продуктовую функциональность. Это hardening уже реализованных frontend, analytics, notification и инфраструктурных потоков. Phase 19, новый identity provider, новый broker, Kubernetes, multi-region и полноценная enterprise-платформа эксплуатации не входят в scope.

## Почему фаза не может быть только инфраструктурным checklist

На момент планирования браузер может отправлять `X-User-Id` прямо в analytics, а frontend-сессия представляет собой кодированный, но не подписанный JSON. Это позволяет подменить identity и является blocking security issue. Поэтому обязательная часть Phase 18 — сделать Next.js BFF реальной границей доверия: браузер работает только с BFF, BFF проверяет криптографически защищённую сессию, а analytics принимает identity headers только вместе с server-only credential.

Это не означает внедрение OAuth/OIDC в Phase 18. Для portfolio deployment достаточно одной явно задокументированной BFF trust boundary. Переход на внешний identity provider требует отдельного архитектурного решения за пределами этой фазы.

## Цель, scope и критерии готовности

### Результат

- В production browser не обращается к analytics напрямую и не может сам назначить `X-User-Id`.
- Frontend-сессия имеет проверяемую подпись, ограниченный срок жизни и безопасные cookie attributes.
- Backend остаётся authoritative для workspace membership и RBAC; BFF только передаёт подтверждённую identity.
- OpenAPI и event contracts проходят автоматическую backward-compatibility проверку.
- Миграции каждого PostgreSQL-сервиса применяются к пустой БД и повторный запуск не оставляет pending migrations.
- Для каждой постоянной PostgreSQL-БД есть проверенный backup/restore drill без перезаписи рабочей БД.
- Rate limits, request limits, timeouts, retries и queue failure handling ограничены, наблюдаемы и описаны.
- Production images и Compose не содержат dev defaults, не публикуют PostgreSQL/Redis и запускаются с минимальными привилегиями.
- Critical browser E2E, API integration, failure-mode drills, dependency/secret audits и основной CI проходят на одном commit.
- `docs/roadmap/18-production-hardening.md` содержит evidence по каждому exit criterion; Phase 18 отмечается `[x]` только после финального checkpoint.

### Разрешённый write scope

- `contracts/**`
- `frontend/**`
- `backend/**`
- `notification/**` — только если сервис действительно создан и закрыт в Phase 14
- `infra/**`
- `scripts/**`
- `.github/**`
- `Makefile`
- `README.md`
- `docs/operations/**`
- `docs/architecture/{01-system-architecture,02-monorepo-and-services,03-frontend-nextjs,07-api-and-integration,08-events-outbox-async,09-infrastructure-deployment-observability,10-testing-and-quality,12-architecture-decisions}.md`
- `docs/roadmap/{18-production-hardening,ROADMAP}.md` — только на финальном checkpoint

### Запрещённый scope

- Phase 19 forecasting и другие новые product features
- смена bounded-context ownership или разделение analytics на новые сервисы
- OAuth/OIDC, SSO или новый identity service без отдельного ADR и отдельного поручения
- Kubernetes, service mesh, multi-region, autoscaling platform или новый message broker
- бессистемное добавление WAF/SIEM/APM-инструментов вместо закрытия найденных рисков
- автоматический destructive rollback миграций или восстановление backup поверх production database
- изменение бизнес-метрик и доменных правил ради прохождения hardening-тестов

## Обязательный preflight-gate

Phase 18 начинается только после выполнения всех пунктов:

- Phase 12–17 отмечены `[x]` в `docs/roadmap/ROADMAP.md`, а их integration checkpoints задокументированы.
- Текущий состав deployable services и persistent stores зафиксирован после Phase 17. Ожидаемый состав: `frontend`, `backend`, `notification`, analytics PostgreSQL, notification PostgreSQL и Redis; фактический состав имеет приоритет.
- Phase 17 предоставляет отдельные liveness/readiness checks, structured logs, correlation context и наблюдаемость workers. Phase 18 проверяет и ужесточает их, но не заново проектирует observability.
- Phase 16 предоставляет измеренный performance baseline. Rate limits и timeouts выбираются выше нормального измеренного p95/p99, а не произвольно.
- `make check`, production image builds и текущий `make integration` проходят до начала hardening. Existing failures сначала устраняются в породившей их фазе.
- OpenAPI, event schemas, generated client и migration inventory не имеют незадокументированного drift.
- На старте создаётся inventory фактических secrets, ports, public endpoints, queues, streams, volumes и внешних зависимостей без вывода secret values в логи.

Если gate не пройден, Phase 18 не маскирует проблему и не меняет порядок roadmap: сначала закрывается соответствующая Phase 12–17.

## Архитектурные решения, которые нужно зафиксировать до кода

### Граница доверия и identity

- Browser обращается к same-origin Next.js BFF endpoint, а не к public analytics URL.
- BFF удаляет любые присланные браузером identity/service headers и формирует их заново из проверенной server-side session.
- Session cookie подписывается криптографически server-only ключом, содержит expiry и не принимается при изменении payload/signature.
- Cookie использует `HttpOnly`, `Secure` в production, `SameSite=Lax`, ограниченный `Path` и явный `Max-Age`.
- Analytics принимает `X-User-Id` только от BFF, доказавшего server-only credential. Secret никогда не имеет префикс `NEXT_PUBLIC_` и не попадает в browser bundle.
- `X-Workspace-Id` остаётся пользовательским выбором, но membership и permission проверяет backend на каждом запросе.
- Health endpoints имеют отдельную минимальную политику доступа и не раскрывают версии dependencies, credentials или exception details.
- Небезопасные BFF mutations проверяют same-origin `Origin`/`Host`; backend CORS не разрешает произвольные origins.
- Текущее `UserIdAuth` считается pre-production contract. Его намеренная замена на BFF trust contract фиксируется ADR до первого production baseline; после baseline breaking changes требуют новой API version.

### Надёжность

- Автоматические HTTP retries допустимы только для idempotent reads и явно идемпотентных commands; login, upload и остальные неидемпотентные writes не повторяются автоматически.
- Каждый network/DB/queue operation имеет конечный timeout. Retry имеет max attempts, backoff и jitter; бесконечных циклов нет.
- Redis не считается единственным источником бизнес-данных. Восстановление cache/queues/streams опирается на PostgreSQL state, outbox/inbox semantics и задокументированный replay.
- Queue message подтверждается только после устойчивого side effect; exhausted messages доступны оператору через failed jobs или dead-letter механизм.
- Deployment использует forward-only migrations. Application rollback не запускает `migrate:rollback`; несовместимое изменение схемы выполняется expand/migrate/contract отдельными deploy steps.

### Backup и recovery

- Резервируются все PostgreSQL databases, которыми владеют deployable services.
- Начальная эксплуатационная цель для demo deployment: RPO не хуже 24 часов и RTO не хуже 2 часов. Это operational target, не внешний SLA.
- Backup создаётся в PostgreSQL custom format, получает checksum и metadata без credentials.
- Restore drill всегда использует новый явно указанный target database/ephemeral container и отказывается перезаписывать source database.
- Redis volume не заменяет PostgreSQL backup. Потеря Redis и порядок восстановления stream/queue state описываются отдельно.

### Security severity gate

- `critical` и `high` findings блокируют закрытие Phase 18.
- `medium` findings либо исправляются, либо получают owner, срок и документированное принятие риска.
- `low` findings могут остаться только в risk register с обоснованием.
- Audit без evidence не считается выполненным: для каждого закрытого finding нужна проверка или regression test.

## План реализации

### Task 1. Зафиксировать production baseline, threat model и ADR trust boundary

**Files:**

- Create: `docs/operations/README.md`
- Create: `docs/operations/threat-model.md`
- Create: `docs/operations/production-readiness.md`
- Modify: `docs/architecture/01-system-architecture.md`
- Modify: `docs/architecture/03-frontend-nextjs.md`
- Modify: `docs/architecture/07-api-and-integration.md`
- Modify: `docs/architecture/09-infrastructure-deployment-observability.md`
- Modify: `docs/architecture/12-architecture-decisions.md`

- [ ] Снять фактическую карту public endpoints, data flows, trust boundaries, persistent data, workers и administrative commands после Phase 17.
- [ ] Описать assets и threat actors: подмена identity/workspace, credential stuffing, session tampering, CSRF, oversized upload, injection, cross-workspace access, dependency compromise, secret leakage, replay, queue poison message и backup theft.
- [ ] Для каждой угрозы записать существующие controls, gap, severity, owner, verification и residual risk.
- [ ] Добавить ADR о Next.js BFF как единственной browser-facing API boundary и server-authenticated identity propagation в analytics.
- [ ] Зафиксировать pre-production исключение для замены `UserIdAuth`; дальнейшая compatibility policy начинает действовать после Phase 18 baseline.
- [ ] Сформировать в `production-readiness.md` матрицу всех exit criteria Phase 18 и места для ссылок на evidence.

Acceptance criteria:

- Ни один public data flow не остаётся без владельца, authentication boundary и transport policy.
- Подмена `X-User-Id` и unsigned session явно имеют blocking severity до исправления.
- ADR не вводит внешний IdP или новый auth service скрыто внутри hardening.

### Task 2. Сделать Next.js BFF реальной границей доверия

**Files:**

- Modify: `contracts/openapi/analytics-v1.yaml`
- Modify: `frontend/src/features/auth/model/session.ts`
- Modify: `frontend/src/features/auth/model/session.test.ts`
- Create: `frontend/src/shared/api/server-identity.ts`
- Create: `frontend/app/api/analytics/[...path]/route.ts`
- Test: `frontend/app/api/analytics/[...path]/route.test.ts`
- Modify: `frontend/src/shared/api/analytics-client.ts`
- Modify: `frontend/src/shared/config/env.ts`
- Modify: `frontend/middleware.ts` или актуальный Next.js replacement после Phase 17
- Modify: browser-facing gateways in `frontend/src/features/**/api/*-gateway.ts`
- Modify: affected gateway/component tests
- Create: `backend/app/Modules/Workspace/Presentation/Middleware/AuthenticateBffRequestMiddleware.php`
- Modify: `backend/app/Modules/Workspace/Presentation/Middleware/AuthenticateUserIdMiddleware.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Security/TrustedBffBoundaryTest.php`
- Modify: `frontend/.env.example`, `backend/.env.example`, `infra/.env.example`

- [ ] Сначала изменить OpenAPI security description: browser не является владельцем identity header; analytics contract доступен через trusted BFF.
- [ ] Заменить plain base64 session на versioned authenticated session value с server-only `SESSION_SECRET`; не писать сам secret или session payload в логи.
- [ ] Добавить tests на valid, expired, malformed и tampered cookie, а также на обязательные cookie attributes.
- [ ] Реализовать same-origin BFF proxy/adapter, который использует allowlist методов и API paths, ограничивает размер тела, не проксирует hop-by-hop headers и sanitizes upstream errors.
- [ ] BFF удаляет входящие `X-User-Id` и service credential, затем добавляет identity из verified session и server-only credential.
- [ ] Перевести browser client на same-origin BFF URL. Удалить `NEXT_PUBLIC_ANALYTICS_API_URL` из production browser path; внутренний URL остаётся server-only.
- [ ] Убрать `userId` из browser-controlled gateway contracts там, где он использовался только для заголовка; UI не должен быть источником identity.
- [ ] Analytics сравнивает service credential constant-time и лишь затем принимает identity header. Login также вызывается через BFF, public health остаётся минимальным исключением.
- [ ] Для unsafe BFF methods проверять `Origin`/`Host`, а backend CORS ограничить известными server origins или полностью отключить для browser access.
- [ ] Возвращать одинаковый публичный ответ для неизвестного пользователя и неверного пароля; не раскрывать stack traces и internal upstream response.

Acceptance criteria:

- Подделанный cookie, прямой `X-User-Id` и browser-supplied service header возвращают `401/403`.
- Валидная BFF session сохраняет существующие RBAC и workspace boundaries.
- Browser bundle и generated static assets не содержат `SESSION_SECRET`, service credential или internal analytics URL.
- Login, logout, session expiry и critical authenticated read/write flows проходят regression tests.

### Task 3. Ввести rate limits, request limits и безопасные HTTP defaults

**Files:**

- Modify: `contracts/openapi/analytics-v1.yaml`
- Create: `backend/app/Providers/RateLimitServiceProvider.php` либо эквивалент в актуальном Laravel bootstrap
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/config/cors.php` when present
- Modify: `backend/app/Modules/DataIngestion/Presentation/Requests/UploadImportRequest.php`
- Create: `backend/tests/Feature/Security/RateLimitTest.php`
- Create: `backend/tests/Feature/Security/RequestLimitTest.php`
- Modify: `frontend/next.config.mjs`
- Test: `frontend/src/shared/security/security-headers.test.ts`
- Modify: `infra/docker-compose.yml`, `infra/docker-compose.vps.yml`
- Modify: `infra/README.md`

- [ ] Использовать Phase 16 measurements для численных limits и записать rationale. Отдельные buckets: login/IP, authenticated reads/user+workspace, writes/user+workspace, imports/user+workspace и expensive evaluation/user+workspace.
- [ ] Rate-limit state хранить в Redis; при недоступности limiter выбрать и задокументировать fail-closed для login/expensive writes и bounded degraded policy для безопасных reads.
- [ ] Возвращать `429` с `Retry-After` и correlation ID; описать ответ в OpenAPI и покрыть contract test.
- [ ] Ограничить upload/body size на BFF, reverse proxy и Laravel validation одним согласованным значением; отклонять запрос до помещения большого файла в память.
- [ ] Уточнить file validation: допустимые форматы, MIME/content checks, имя файла без path traversal и sanitized validation errors.
- [ ] Добавить production headers: CSP, `frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` и HSTS на TLS edge. Не ломать необходимые Next.js assets/API calls чрезмерно широкой или фиктивной CSP.
- [ ] Настроить trusted proxy/host policy так, чтобы rate-limit IP и HTTPS detection не доверяли произвольным forwarded headers.

Acceptance criteria:

- Login brute-force, burst чтений и expensive writes получают предсказуемый `429`, а разные authenticated principals не делят один случайный bucket.
- Oversized и invalid uploads завершаются до queue dispatch и не оставляют partial import state.
- Security headers проверяются автоматизированно на production build.
- Нормальная нагрузка из Phase 16 не throttled выбранными значениями.

### Task 4. Ограничить timeouts, retries и queue failure handling

**Files:**

- Create: `docs/operations/failure-modes.md`
- Modify: `frontend/src/shared/api/analytics-client.ts` и server-side transport helpers
- Modify: `backend/config/database.php`
- Modify: `backend/config/queue.php`
- Modify: relevant jobs in `backend/app/**/Jobs/*.php`
- Modify: outbox publisher/commands from Phase 13
- Modify: notification consumer/config from Phase 14
- Create if absent: `backend/database/migrations/*_create_failed_jobs_table.php`
- Modify: `infra/docker-compose.yml`, `infra/docker-compose.vps.yml`
- Create: `backend/tests/Feature/Resilience/QueueFailureHandlingTest.php`
- Create: `backend/tests/Feature/Resilience/DependencyTimeoutTest.php`
- Test: notification retry/DLQ tests from Phase 14

- [ ] Составить failure-mode matrix для unavailable/slow PostgreSQL, Redis, analytics, notification worker и external proxy; для каждого указать detection, user-visible behavior, data safety и recovery command.
- [ ] Ввести конечные connect/request/statement timeouts. Значения опираются на Phase 16 p99 и остаются короче upstream/proxy timeout, чтобы приложение завершало работу контролируемо.
- [ ] Централизовать frontend/server fetch timeout и normalized error mapping. UI показывает безопасное retryable состояние без internal exception.
- [ ] Запретить автоматический retry неидемпотентных HTTP commands. Для idempotent reads использовать небольшой bounded retry только на transient network/5xx, с backoff и jitter.
- [ ] Для каждого queue job явно проверить `$tries`, timeout, backoff, uniqueness/idempotency и `failed()` behavior; worker options не должны противоречить job timeout и Redis `retry_after`.
- [ ] Обеспечить рабочее хранилище failed jobs, sanitized failure logs, commands просмотра/retry и runbook. Payload/secrets/PII не попадают в exception output.
- [ ] Зафиксировать recovery для outbox `failed`, notification pending/DLQ и import jobs. Retry сохраняет исходный event/import identity и не создаёт duplicate side effects.
- [ ] Настроить graceful shutdown и достаточный stop grace period для workers; message не подтверждается до commit.

Acceptance criteria:

- Нет infinite timeout/retry и retry storm при падении dependency.
- Потеря процесса в commit-before-ack window приводит к idempotent redelivery, а не к потере или дублированию бизнес-эффекта.
- Exhausted job/event виден оператору, коррелируется и восстанавливается одной документированной командой.
- Failure tests не требуют ослабления health/readiness semantics Phase 17.

### Task 5. Добавить автоматическую compatibility-проверку HTTP и event contracts

**Files:**

- Create: `scripts/check-contract-compatibility.sh`
- Create: `contracts/compatibility-policy.md`
- Modify: `frontend/package.json`, `frontend/package-lock.json` only if a local checker is selected
- Modify: `.github/workflows/ci.yml`
- Extend: `backend/tests/Feature/ApiContractTest.php`
- Extend: event contract tests created in Phase 13/14
- Modify: `contracts/README.md`

- [ ] Зафиксировать policy: additive optional fields/endpoints разрешены; удаление/переименование, tightening input, изменение status/media type/security semantics и новые required response fields считаются breaking.
- [ ] Сравнивать OpenAPI с merge-base/target branch purpose-built checker-ом; версию checker pin, а временные файлы создавать вне tracked tree.
- [ ] Для намеренного breaking change требовать новую API version или явно задокументированный pre-baseline exception. После Phase 18 silent override запрещён.
- [ ] Продолжить lint, code generation и `git diff --exit-code` generated client после compatibility check.
- [ ] Проверять все canonical event examples против JSON Schema и запрещать несовместимое изменение опубликованного `v1`.
- [ ] Расширить runtime contract tests на security errors, rate-limit response и критические success/error responses, а не только health.

Acceptance criteria:

- CI fixture доказывает, что удаление endpoint/required field блокируется, а additive optional field проходит.
- Generated client drift блокирует CI.
- `alert.triggered.v1` и другие опубликованные events Phase 13–14 не меняют семантику незаметно.

### Task 6. Доказать migration safety на чистых базах

**Files:**

- Create: `scripts/verify-migrations.sh`
- Create: `docs/operations/migrations.md`
- Create: `backend/tests/Feature/Infrastructure/MigrationSmokeTest.php`
- Create when applicable: `notification/tests/Feature/Infrastructure/MigrationSmokeTest.php`
- Modify: `.github/workflows/ci.yml`
- Modify: `Makefile`
- Modify only when an issue is proven: existing/new migration files in owning service

- [ ] Инвентаризировать migrations по сервисам, одинаковые timestamps, порядок foreign keys/indexes, nullable/default strategy и потенциально destructive operations.
- [ ] На отдельных пустых PostgreSQL databases применить полный migration chain с `--force --no-interaction`, проверить schema/constraints/indexes и отсутствие pending migrations при повторном запуске.
- [ ] Отдельно применить seed/demo data только после успешной schema migration; production deployment не должен зависеть от seed.
- [ ] Проверить upgrade path от последнего зафиксированного pre-Phase-18 schema baseline. Если production baseline ещё не существовал, явно записать это и сохранить текущую schema как первый baseline.
- [ ] Для новых изменений применять expand/migrate/contract; не переписывать уже опубликованные migrations после baseline.
- [ ] Проверить, что application containers не требуют superuser DB role для runtime; migration role и runtime role разделить, если deployment это поддерживает.

Acceptance criteria:

- `make migration-check` с нуля проходит для каждой PostgreSQL database и подтверждает idempotent second run.
- Ни одна migration не требует ручного редактирования данных на чистой установке.
- Destructive/locking risk каждого найденного изменения либо устранён, либо имеет explicit deployment step и rollback-by-roll-forward plan.

### Task 7. Реализовать безопасный backup/restore drill

**Files:**

- Create: `scripts/backup-postgres.sh`
- Create: `scripts/restore-postgres.sh`
- Create: `scripts/verify-backup-restore.sh`
- Create: `docs/operations/backup-restore.md`
- Modify: `Makefile`
- Modify: `.github/workflows/ci.yml` or scheduled workflow when appropriate
- Modify: `infra/README.md`

- [ ] Backup script принимает явное имя service/database и output directory, использует credentials через environment/`.pgpass`, создаёт custom-format dump, checksum и metadata с timestamp/schema version.
- [ ] Не писать password, connection URL с password, row payload или secret environment в stdout/stderr.
- [ ] Restore script требует новый explicit target, проверяет checksum и совместимую PostgreSQL major version, затем восстанавливает schema/data без обращения к source database.
- [ ] Встроить guard: source и target identifiers не могут совпадать; production-like names требуют отдельного явного флага, который не используется в CI drill.
- [ ] Restore drill поднимает ephemeral PostgreSQL, применяет restore и проверяет ключевые row counts, constraints, workspace isolation fixtures и migration status.
- [ ] Повторить drill для analytics DB и notification DB. Если Phase 14 изменит список stores, использовать фактический inventory Task 1.
- [ ] Описать daily schedule, off-host encrypted storage, 7 daily restore points, access control, RPO/RTO, проверку restore на каждом release checkpoint и процедуру удаления старых backups.
- [ ] Описать потерю Redis: cache очищается, queues/streams восстанавливаются согласно outbox/inbox ownership; не обещать восстановление без проверенного replay path.

Acceptance criteria:

- `make backup-restore-drill` восстанавливает каждую БД в ephemeral target и завершается без доступа к исходной БД на запись.
- Повреждённый checksum, совпавший source/target и отсутствующий dump завершаются до restore.
- Документация даёт оператору пошаговый recovery без неявного destructive действия.

### Task 8. Ужесточить secrets, dependencies, images и production Compose

**Files:**

- Modify: `.gitignore`
- Modify: `frontend/.env.example`, `backend/.env.example`, `notification/.env.example` when present, `infra/.env.example`
- Create: `scripts/validate-production-env.sh`
- Create: `scripts/scan-secrets.sh` or pinned CI secret-scanner configuration
- Create: `.github/dependabot.yml`
- Modify: `.github/workflows/ci.yml`
- Modify: service Dockerfiles
- Modify: `infra/docker-compose.yml`, `infra/docker-compose.vps.yml`
- Create: `docs/operations/secrets-and-dependencies.md`
- Modify: `Makefile`

- [ ] Классифицировать env variables на public build-time, server configuration и secrets. Любой secret с `NEXT_PUBLIC_` является blocking finding.
- [ ] Production preflight отклоняет missing/default/short secrets, `APP_DEBUG=true`, non-HTTPS public URLs, `latest` image tags и accidental publication PostgreSQL/Redis ports.
- [ ] Development examples остаются запускаемыми, но имеют явную маркировку non-production; CI создаёт ephemeral values и не превращает example credentials в production defaults.
- [ ] Добавить secret scanning tracked history/diff с pinned tool version и allowlist только для доказанных false positives.
- [ ] Запускать `npm audit` для production dependencies и `composer audit` для locked dependencies всех PHP services; high/critical findings блокируют release либо имеют документированное исключение с датой.
- [ ] Настроить Dependabot для npm, Composer, GitHub Actions и Docker с ограниченной частотой/группировкой, чтобы обновления оставались reviewable.
- [ ] Pin CI actions по immutable revision согласно принятой policy; base images фиксировать достаточно точно для воспроизводимости и регулярно обновлять через review.
- [ ] Проверить non-root user, minimal runtime packages, отсутствие package managers/source/dev dependencies в final image, `no-new-privileges`, dropped capabilities, read-only filesystem/tmpfs там, где это совместимо с Laravel/Next.js runtime.
- [ ] В production Compose не публиковать DB/Redis, не монтировать source tree, не использовать shared analytics DB credentials для notification и не передавать frontend secrets как build args.

Acceptance criteria:

- `make security-check` завершает secret/dependency/config audits с нулём unresolved critical/high findings.
- Production config не стартует с example/default secrets и mutable `latest` images.
- Final images работают non-root и содержат только runtime artifacts.
- Runtime каждого сервиса получает только нужные ему secrets и database credentials.

### Task 9. Написать deployment, rollback и incident runbooks

**Files:**

- Create: `docs/operations/deployment.md`
- Create: `docs/operations/rollback.md`
- Create: `docs/operations/incidents.md`
- Create: `scripts/production-preflight.sh`
- Create: `scripts/smoke-production.sh`
- Modify: `infra/README.md`
- Modify: `README.md`
- Modify: `Makefile`

- [ ] Описать первый deploy и upgrade отдельно: prerequisites, immutable image tags/digests, env validation, backup, migration, service start order, readiness wait, smoke tests и evidence capture.
- [ ] Порядок учитывает все фактические deployables: infrastructure → backups → forward migrations → HTTP services → scheduler/workers/consumers → frontend → smoke tests.
- [ ] Rollback разделяет application rollback и database roll-forward. Запретить автоматический `migrate:rollback` в production runbook.
- [ ] Для контрактных изменений описать совместимое окно, в котором old/new frontend и backend могут работать одновременно.
- [ ] `production-preflight` выполняет read-only checks: Compose render, required variables без печати values, image tag policy, network/volume presence и backup freshness.
- [ ] `smoke-production` по умолчанию делает только non-destructive health/readiness/authenticated reads. Mutation smoke требует отдельного test workspace и explicit flag.
- [ ] Incident runbook связывает correlation ID с logs/metrics, описывает triage DB/Redis/queue/outbox/notification и критерии rollback/recovery.
- [ ] Caddy/TLS example содержит HTTPS redirect, HSTS после проверки TLS, request/body limits и timeouts, согласованные с приложением.

Acceptance criteria:

- Новый оператор может развернуть clean environment, применяя только tracked docs/config и отдельно переданные secrets.
- Ошибка любого preflight step останавливает deploy до migration/service mutation.
- Rollback procedure не обещает обратимость destructive schema change.

### Task 10. Автоматизировать failure-mode и critical E2E verification

**Files:**

- Create: `scripts/verify-failure-modes.sh`
- Modify: `scripts/verify-integration.sh`
- Create: `frontend/playwright.config.ts`
- Create: `frontend/e2e/critical-path.spec.ts`
- Create: `frontend/e2e/security-boundary.spec.ts`
- Modify: `frontend/package.json`, `frontend/package-lock.json`
- Modify: `.github/workflows/ci.yml`
- Modify: `Makefile`

- [ ] Failure script использует отдельный Compose project и throwaway volumes, а cleanup выполняется через trap. Он не принимает production project name.
- [ ] Проверить остановку Redis, analytics PostgreSQL, notification service и worker по одному: readiness меняется корректно, liveness не врёт, HTTP timeout ограничен, после восстановления backlog обрабатывается без duplicate side effects.
- [ ] Проверить poison/malformed event, exhausted queue job, outbox retry и notification DLQ recovery согласно contracts Phase 13/14.
- [ ] Добавить Playwright только для небольшого critical path: login через BFF, открытие основных analytics views, workspace isolation/RBAC, один безопасный controlled write в test workspace, logout и истёкшая/подделанная session.
- [ ] E2E использует role/label/test-id, а не нестабильные CSS selectors или sleeps; readiness wait заменяет фиксированные задержки.
- [ ] Integration script перестаёт доказывать auth прямым доверенным `X-User-Id` из browser perspective. Internal backend assertions используют отдельный test-only credential и явно отделены от user flow.
- [ ] При падении CI всегда собирать sanitized container logs и Playwright artifacts; secrets, cookies и request bodies не публиковать.

Acceptance criteria:

- `make failure-check`, `make integration` и `make e2e` проходят независимо на чистых throwaway stacks.
- Recovery после dependency restart не требует удаления volume или `migrate:fresh`.
- Critical E2E доказывает, что spoofed identity/session не даёт доступ к чужому workspace.

### Task 11. Собрать единый release gate и провести security review

**Files:**

- Modify: `.github/workflows/ci.yml`
- Create when separation improves clarity: `.github/workflows/security.yml`
- Modify: `Makefile`
- Modify: `docs/operations/production-readiness.md`
- Modify: `docs/operations/threat-model.md`

- [ ] Собрать aggregate targets без скрытого state: `check`, `security-check`, `contract-compatibility`, `migration-check`, `backup-restore-drill`, `failure-check`, `integration`, `e2e`, production image builds.
- [ ] В CI явно задать dependencies между fast static checks, database checks, image builds и expensive integration/E2E; expensive jobs не стартуют после failed fast gate.
- [ ] Проверить generated artifacts, dirty tree после generation и отсутствие untracked operational evidence, которое необходимо сохранить.
- [ ] Провести review по OWASP-oriented threat model, RBAC/cross-workspace matrix, mass assignment/validation, SQL/raw queries, uploads, logs, errors, secrets, dependencies, container config и recovery paths.
- [ ] Для каждого finding обновить status/evidence. Critical/high должны быть закрыты и иметь regression test; accepted medium/low имеют owner и срок.
- [ ] Проверить, что hardening не изменил business semantics, DDD boundaries или независимость deployable services.

Acceptance criteria:

- Один CI run на final commit зелёный по всем обязательным jobs.
- Security review не содержит open critical/high findings.
- Production-readiness matrix ссылается на команды, тесты, runbooks и CI evidence, а не на утверждения без проверки.

### Task 12. Выполнить integration checkpoint и закрыть Phase 18

**Files:**

- Modify: `docs/roadmap/18-production-hardening.md`
- Modify: `docs/roadmap/ROADMAP.md`
- Verify: all files from Tasks 1–11

- [ ] На чистом checkout создать ephemeral production secrets, собрать все production images и развернуть clean environment строго по `docs/operations/deployment.md`.
- [ ] Выполнить migrations на empty databases, затем повторный migration run.
- [ ] Запустить full quality, contract compatibility, security, migration, backup/restore, failure-mode, API integration и browser E2E gates.
- [ ] Выполнить manual review rendered production Compose: public ports, networks, volumes, secret exposure, user/capabilities, healthchecks, graceful shutdown и immutable images.
- [ ] Сопоставить evidence со всеми exit criteria roadmap и матрицей production readiness.
- [ ] Добавить в `18-production-hardening.md` разделы `Прогресс` и `Проверка завершения`: дата, commit, команды, результаты, закрытые findings, accepted residual risks, RPO/RTO drill и следующий шаг.
- [ ] Только после всех предыдущих пунктов заменить Phase 18 `[ ]` на `[x]` в `docs/roadmap/ROADMAP.md`.
- [ ] Не начинать Phase 19 в этом изменении.

Обязательные команды финального checkpoint уточняются по фактическому Makefile после Phase 17, но должны включать эквивалент:

```bash
make check
make security-check
make contract-compatibility
make migration-check
make backup-restore-drill
make failure-check
make integration
make e2e
docker compose --env-file <ephemeral-env> -f infra/docker-compose.yml build
docker compose --env-file <ephemeral-env> -f infra/docker-compose.yml config
docker compose --env-file <ephemeral-env> -f infra/docker-compose.vps.yml config
git diff --check
git status --short
```

Expected:

- все команды завершаются с exit code `0`;
- clean environment deployable по tracked документации;
- migrations работают с empty DB;
- backup каждой PostgreSQL database восстановлен в ephemeral target и проверен;
- critical E2E и recovery drills проходят;
- unresolved critical/high security findings отсутствуют;
- generated artifacts синхронизированы, в diff нет secrets и случайных runtime-файлов.

## Матрица exit criteria Phase 18

| Exit criterion | Основное evidence | Блокирующее условие |
| --- | --- | --- |
| Нет blocking security issues | Threat model, security review, BFF/session tests, secret/dependency scans | Любой open critical/high finding |
| Clean environment deployable по документации | Deployment runbook, production preflight, clean-stack CI job | Ручной незадокументированный шаг или default secret |
| Migrations работают с empty DB | `make migration-check`, per-service migration smoke tests | Ошибка fresh migration или pending second run |
| Failure behavior описан | Failure-mode matrix, automated dependency drills, incident runbook | Неограниченный timeout/retry или недоказанный recovery |
| CI green | Final commit CI run | Любой обязательный skipped/failed job |
| Critical E2E проходят | Playwright critical/security specs и API integration | Spoofing/cross-workspace доступ или flaky retry/sleep workaround |
| Backup/restore operational | Per-database checksum + ephemeral restore drill | Backup без успешного restore или destructive default target |
| API compatibility контролируется | OpenAPI diff, event schema tests, generated client drift check | Silent breaking change опубликованного contract |

## Порядок исполнения и ownership

Работы идут последовательно там, где меняется общая граница:

1. Preflight, threat model и ADR.
2. OpenAPI/security contract freeze.
3. BFF/session/backend trust boundary.
4. Rate limits, timeouts, retries и failure semantics.
5. Compatibility и migration gates.
6. Backup/restore и production infrastructure.
7. Deployment/failure/E2E automation.
8. Независимый security review и integration checkpoint.

После фиксации security contract допускается параллельная работа только с непересекающимся write scope:

- frontend BFF/session;
- backend rate limiting/queue resilience;
- migration и backup scripts/docs;
- CI/dependency/container audit.

Высококонфликтные файлы `contracts/openapi/analytics-v1.yaml`, `.github/workflows/ci.yml`, `infra/docker-compose*.yml`, `Makefile`, env examples и architecture docs изменяет интегратор последовательно. Один reviewer не должен проверять собственный security-sensitive change как единственный reviewer.

## Риски и меры контроля

- **Phase 18 планируется до завершения Phase 12–17.** Все перечисленные пути и service names перепроверяются в Task 1; план не разрешает начинать фазу раньше roadmap.
- **BFF refactor затрагивает много gateway call sites.** Сначала фиксируется один transport/session abstraction, затем выполняется механическая миграция без дублирования auth headers по features.
- **Rate limits могут ломать нормальный BI flow.** Значения выводятся из Phase 16 baseline и проверяются E2E, а не выбираются по ощущениям.
- **Retry может дублировать writes.** По умолчанию writes не повторяются; исключение требует доказанной idempotency key/identity.
- **Backup может создать ложное чувство безопасности.** Exit criterion — успешный restore и проверка данных, а не наличие dump-файла.
- **Security scanners дают false positives и меняющиеся advisory feeds.** Исключения versioned, узкие, с owner/expiry; critical/high не игнорируются общим allowlist.
- **Hardening может разрастись в platform engineering.** Инструменты выбираются минимальные для текущего single-host portfolio deployment; Kubernetes, новый broker и внешний IdP исключены.

## Definition of Done

Phase 18 завершена только если одновременно выполнено следующее:

- preflight phases закрыты, архитектурные документы и ADR актуальны;
- browser identity нельзя подделать через cookie или headers;
- backend RBAC/workspace checks остаются authoritative;
- rate limits, sizes, timeouts, retries и queue failures имеют конечные политики и tests;
- HTTP/event compatibility проверяется автоматически;
- все service migrations проходят на empty PostgreSQL;
- backup/restore проверен для каждой persistent PostgreSQL database;
- production Compose/images/env проходят hardening и secret/dependency audits;
- deployment, rollback, incident и failure-mode runbooks воспроизводимы;
- API integration, critical browser E2E и failure drills проходят;
- final CI green на одном commit, open critical/high findings нет;
- roadmap closure содержит фактическое evidence, а Phase 19 не начата.

## Допущения

- К моменту старта Phase 18 Phase 14 может добавить `notification/`, вторую PostgreSQL и новые Compose services; Task 1 обязан заменить ожидаемый inventory фактическим.
- Phase 15 сохраняет backend-authoritative authorization, Phase 16 даёт performance baseline, Phase 17 — health/observability primitives.
- Текущий single-host/VPS deployment остаётся целевой production demonstration; внешний managed backup storage настраивается оператором, а репозиторий документирует формат, encryption/access requirements и restore drill.
- Новые tooling dependencies добавляются только при отсутствии уже доступного эквивалента, фиксируются lock/version и не проникают в application runtime без необходимости.
- Существующие незакоммиченные изменения Phase 12/13 принадлежат текущей работе и не изменяются при создании этого плана.
