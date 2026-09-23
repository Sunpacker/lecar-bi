# RAG Support Chat — инструкция для агентов

## Цель

Реализовать в AutoBI чат поддержки на RAG, сохранив текущую архитектуру проекта:

- **Frontend:** Next.js
- **Backend:** Laravel, DDD
- **DB:** PostgreSQL
- **Vector search:** pgvector
- **Keyword search:** PostgreSQL FTS
- **Async jobs:** Laravel Queue
- **Transport:** HTTP + SSE streaming

Не добавлять отдельный Python/FastAPI-сервис или отдельную vector DB без явной необходимости.

---

## Архитектурные границы

Создать два bounded context:

### `Support`
Отвечает за:
- диалоги;
- сообщения;
- feedback;
- вызов RAG;
- историю генераций;
- usage/latency/metadata.

### `KnowledgeBase`
Отвечает за:
- источники знаний;
- документы;
- чанки;
- embeddings;
- индексацию;
- retrieval.

Next.js отвечает только за UI и взаимодействие с API. Вся RAG-логика должна находиться в Laravel.

---

## Модель знаний

Поддерживать два типа данных:

1. **Глобальная база AutoBI** — документация, FAQ, инструкции.
2. **Tenant knowledge** — приватные документы организации.

Для приватных данных retrieval обязан применять фильтрацию по `tenant_id` и правам пользователя **до передачи контекста в LLM**.

Пример ключевых полей `knowledge_chunks`:

- `id`
- `document_id`
- `tenant_id nullable`
- `content`
- `embedding`
- `search_vector`
- `metadata`
- `chunk_index`
- `content_hash`

---

## Retrieval

Использовать гибридный поиск:

1. получить embedding вопроса;
2. выполнить vector search через pgvector;
3. выполнить PostgreSQL FTS;
4. объединить результаты;
5. применить tenant/ACL-фильтры;
6. выбрать top chunks;
7. при необходимости выполнить reranking;
8. передать только релевантный контекст в LLM.

Если релевантного контекста недостаточно, модель не должна выдумывать ответ.

---

## Ingestion pipeline

Индексацию выполнять асинхронно через Laravel Queue:

`Source → Extract → Normalize → Chunk → Metadata → Embedding → PostgreSQL`

На MVP поддержать документацию/FAQ проекта. Загрузку пользовательских PDF/DOCX и внешние интеграции добавить позже.

---

## API

Минимальный контракт:

- `POST /api/v1/support/conversations`
- `GET /api/v1/support/conversations`
- `GET /api/v1/support/conversations/{id}`
- `POST /api/v1/support/conversations/{id}/messages`
- `GET /api/v1/support/conversations/{id}/messages`
- `POST /api/v1/support/messages/{id}/feedback`

Ответ ассистента отдавать streaming через SSE.

---

## UI

MVP интерфейса должен поддерживать:

- чат;
- streaming ответа;
- Markdown;
- историю диалогов;
- ссылки на источники;
- retry;
- feedback 👍/👎;
- обработку ошибок.

Не переносить бизнес-логику RAG во frontend.

---

## Абстракции AI

Доменная и application-логика не должна зависеть от конкретного провайдера.

Использовать интерфейсы уровня:

- `ChatModel`
- `EmbeddingModel`
- `Reranker`

Конкретные OpenAI/Anthropic/Google-адаптеры размещать в Infrastructure.

---

## Persistence и observability

Для каждого ответа сохранять:

- conversation/message ID;
- model;
- retrieved chunk IDs;
- retrieval scores;
- token usage;
- latency;
- status/error;
- feedback пользователя.

Генерации не должны существовать только в client state.

---

## Порядок реализации

1. `Support` domain/application.
2. `KnowledgeBase` domain/application.
3. pgvector + schema/migrations.
4. ingestion pipeline.
5. hybrid retrieval.
6. RAG orchestrator.
7. chat API + SSE.
8. Next.js chat UI.
9. citations + feedback.
10. retrieval/generation tracing.
11. RAG evaluation.
12. tenant knowledge base.

---

## MVP

Первая рабочая версия должна реализовать только:

`Docs/FAQ → chunking → embeddings → pgvector + FTS → Laravel RAG → LLM → SSE → Next.js → citations + feedback`

Не добавлять на MVP:

- LangChain;
- GraphRAG;
- knowledge graph;
- отдельный AI microservice;
- отдельную vector DB;
- сложную агентную систему.

---

## Критерии готовности

Фича считается завершённой, если:

- пользователь может создать диалог и задать вопрос;
- ответ приходит streaming;
- ответ основан на найденных чанках;
- показаны источники;
- история сохраняется;
- приватные знания tenant'ов изолированы;
- отсутствие релевантного контекста не приводит к выдуманному ответу;
- ошибки AI/retrieval корректно обрабатываются;
- ключевые сценарии покрыты автоматическими тестами;
- архитектурные правила проекта и DDD не нарушены.

---

## Правило расширения

Сначала довести MVP и измерить качество retrieval. Только после этого добавлять reranking, query rewriting, conversation-aware retrieval, file uploads и tool calling.
