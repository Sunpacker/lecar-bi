# Phase 20 — RAG Support Chat

[Индекс и правила roadmap](ROADMAP.md) · [Спецификация](../architecture/rag-support-chat.md)

## Зависимости

- Phase 15: готовая workspace/auth boundary и RBAC.
- Phase 16–18: performance baseline, observability и production hardening до публичного выпуска.
- Утверждённая политика внешней обработки данных и выбранные chat/embedding providers.

Явная задача на реализацию позволяет готовить feature до завершения зависимостей выпуска. Это не
закрывает Phase 16 и не разрешает production rollout без checkpoint ниже.

## Scope

- `Support` и `KnowledgeBase` внутри analytics-service.
- Версионированный разрешённый русскоязычный Markdown/FAQ corpus.
- PostgreSQL pgvector + FTS, воспроизводимый ingestion и hybrid retrieval.
- Durable generation, API/SSE, Next.js UI, citations, feedback, лимиты и retention.
- Детерминированные AI adapters для CI; production adapters подключают Google через Neuron AI:
  `gemma-4-26b-a4b-it` и `gemini-embedding-2` с 768 измерениями.

## Exit Criteria

- Выполнены все exit criteria раздела «Exit criteria MVP» спецификации RAG.
- Все 10 HTTP-операций и SSE протокол совпадают с OpenAPI и generated frontend types.
- PostgreSQL integration, API/contract, frontend/E2E и infrastructure проверки проходят.
- Versioned holdout из 60 вопросов проходит release gates на утверждённых provider/model/profile.
- Provider data policy, стоимость, latency и backup retention записаны в evaluation report.

## Integration Checkpoint

Интегратор подтверждает миграции на чистой PostgreSQL с pgvector, contract compatibility,
изоляцию workspace/user, отказ доступа после revoke, queue recovery, proxy streaming,
безопасное логирование, evaluation report и отсутствие деградации основных BI endpoints.

## Прогресс

- Добавлен этап и зафиксированы зависимости, scope и checkpoint.
- Зафиксированы corpus/evaluation datasets, OpenAPI на 10 операций и generated frontend types.
- Реализованы Support/KnowledgeBase, pgvector+FTS ingestion/retrieval, durable generation/SSE,
  отдельные AI queues, лимиты/retention и Next.js UI с reconnect/citations/feedback.
- Retrieval закрепляет query model за profile активного build; все support-вызовы идут через BFF с
  подписанной session и server-only transport credential, прямой forged `X-User-Id` отклоняется.
- Добавлены deterministic unit/component/contract проверки и отдельный PostgreSQL integration suite.
- На одноразовом dev Compose подтверждены чистые миграции PostgreSQL 16 + pgvector, повторный
  ingestion, revoke/CAS, hybrid retrieval, API isolation/idempotency и полный BFF → queue worker →
  SSE путь с citation, reconnect, feedback и history.
- Google provider и demo data policy зафиксированы. Baseline holdout выполнен, но retrieval,
  answerable, adversarial и citation gates не прошли; human correctness review не выполнен.
- Production Compose проверен статически; production proxy/load/failure-recovery checkpoint не запускался.

Следующий шаг: стабилизировать provider transport, получить clean calibration нового зафиксированного
retrieval profile, затем один раз повторить holdout и human review. После этого проверить production
proxy buffering, нагрузку, backup retention и recovery.
