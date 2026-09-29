# 09. Infrastructure, Deployment and Observability

## Containerization

Each deployable service must have its own Docker image.

The local environment must allow the entire system to run through a single development orchestration file.

## Core Infrastructure Components

The infrastructure includes:

- `frontend` — Next.js UI / BFF;
- `backend` — Laravel Analytics Service;
- `postgres` (analytics) — analytics and outbox storage;
- `notification` — Laravel Notification Service (HTTP health/readiness endpoints);
- `notification-worker` — the `notifications:consume` event consumption process;
- `notification-postgres` — a separate PostgreSQL database and volume for the notification service;
- `redis` — shared integration event transport (Streams), cache, and queues:
  - database 0: default / queues;
  - database 1: selective analytics cache (a separate isolated DB, `volatile-ttl`);
  - database 2: Redis Stream integration events (`autobi.integration-events`).

The notification service does not receive credentials for analytics `postgres` and has no network dependency on it.

## Independent Deployment

Frontend, Analytics, and Notification are deployed independently:

- Each service has its own Dockerfile and build target.
- A change to Notification Service does not require rebuilding Analytics or Frontend.
- Stopping `notification` or `notification-worker` does not affect Analytics or Frontend readiness/liveness.
- Migration scripts run independently for each database.

For the VPS, backend and notification are built after CI and published to GHCR with the full commit SHA as the tag. The server runs prebuilt images through `infra/docker-compose.vps.yml`; frontend is absent from this Compose file. A release includes Compose and observability configuration, while the secret env file and backups are stored outside release directories. Both databases are backed up before migrations. Restoring previous images does not roll back the schema, so production migrations must be compatible with the previous release. The detailed procedure is in `infra/README.md`.

## Configuration

Environment configuration must be stored outside business code.

Secrets must not be stored in the repository.

## Observability

The system should gradually incorporate:

- structured logs;
- request identifiers;
- correlation identifiers;
- error tracking;
- metrics;
- distributed tracing;
- health checks.

## Correlation ID

A common correlation identifier must be preserved as a single user request passes through multiple services.

This is necessary to find related log entries and support distributed tracing later.

## Structured Logging

Logs must be machine-readable.

Avoid relying on arbitrary unstructured messages as the sole source of information.

### Sanitized Fail-Open Logging for Cache Failures

When Redis is unavailable, times out, or degrades, the infrastructure cache logs a warning:

- The context includes only technical metadata: dataset name (`dataset`), `workspace_id`, canonical operation (`operation`), and error class (`RedisException`).
- Response payloads, personal data, authorization headers, and SQL parameters are strictly excluded.
- The error does not interrupt the request: the system performs a fail-open fallback to a direct PostgreSQL Read Model call.

## Health Checks

Each service provides technical health checks:

- **Analytics Service:** `GET /api/v1/health` — checks HTTP API availability and service readiness.
- **Notification Service:**
  - `GET /api/v1/health/live` — liveness probe: verifies that the HTTP process is running and accepting requests.
  - `GET /api/v1/health/ready` — readiness probe: checks availability of the notification service's local PostgreSQL and the Redis Stream transport. Analytics is not a runtime dependency and is not polled.
- Background worker state is logged in structured form (heartbeat / processed count). Stopping a worker does not affect web process liveness.

## Observability Stack

A centralized stack for collecting metrics, logs, and dashboards has been implemented:

- **Prometheus** (`prom/prometheus:v2.54.1`): collects metrics through internal `/metrics` endpoints on `backend:8080` and `notification:8081` every 10 seconds. Evaluates operational alert rules (`TargetDown`, `ReadinessFailed`, `NotificationWorkerHeartbeatMissing`, `OutboxBacklogGrowing`, `OutboxOldMessageStuck`, `IntegrationDeadLetterMessagesPresent`, `HttpHighErrorRate`).
- **Grafana Loki** (`grafana/loki:3.1.1`): unified storage for structured JSON logs with a 7-day retention policy (`retention_period: 168h`).
- **Grafana Alloy** (`grafana/alloy:v1.3.1`): container log collector reading stdout/stderr through `/var/run/docker.sock`, with normalization of low-cardinality labels (`service`, `container`).
- **Grafana** (`grafana/grafana:11.2.0`): automatic provisioning of data sources (Prometheus, Loki) and built-in dashboards:
  - _Service Overview_: availability, RPS by route, 5xx errors, p95 latency, health dependencies;
  - _Background Processes_: outbox backlog, message age, worker heartbeat, stream lag, dead-letter queue, job executions;
  - _Log Search_: cross-service log search by `request_id`, `correlation_id`, `event_id`.

The `/metrics` endpoints are isolated within the `internal` Docker network and are not exposed to the public internet through the reverse proxy.
Grafana is also connected to a separate `grafana-host` network so Docker can publish its interface only on the VPS's `127.0.0.1:3001`.

## Further Evolution

- **OpenTelemetry / Tempo**: add distributed tracing when end-to-end latency profiling is needed along Next.js → Analytics → Outbox → Notification.
- **External alert channels**: configure Alertmanager (Telegram, Email, Webhook) through environment secrets.

The choice of a specific tool does not affect the Domain Layer.
