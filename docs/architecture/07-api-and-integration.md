# 07. API and Integration

## API as a Public Boundary

Laravel must provide a stable, explicitly defined API.

The API is treated as a contract between:

- Next.js and Laravel;
- Laravel and future services;
- internal and external integrations.

## OpenAPI

The main HTTP API must be described using OpenAPI.

OpenAPI is used for:

- documenting endpoints;
- describing request and response models;
- generating the TypeScript client;
- compatibility checks;
- contract testing;
- managing API changes.

## Versioning

The public API must have an explicit version.

Incompatible changes must not silently break existing consumers.

## Typed Client

The frontend must use a generated API client.

Manual duplication of API types should be minimized.

## Synchronous Integrations

HTTP is suitable for scenarios where:

- a response is needed immediately;
- the operation follows a request-response pattern;
- the calling service needs the execution result.

## Asynchronous Integrations

Events are suitable for scenarios where:

- an immediate response is not required;
- one operation may interest multiple services;
- loose coupling is needed;
- processing can run independently.

### Analytics → Notification Integration

The analytics and notification services integrate through asynchronous events:

1. **Transport:** Redis Stream `autobi.integration-events`.
2. **Publication:** The analytics service records the event in PostgreSQL Outbox in the same transaction as the business change (Transactional Outbox). A background worker publishes the event to Redis Stream using `XADD` with the fields `event_id`, `event_type`, `event_version`, and `payload` (the JSON-serialized canonical envelope).
3. **Consumption:** The notification service reads the stream through the `notification-service-v1` consumer group using `XREADGROUP` and `XAUTOCLAIM`.
4. **Autonomy:** The notification service never makes synchronous HTTP calls back to the analytics API or accesses the analytics DB. The canonical `alert.triggered.v1` event payload contains all information needed to create the notification's title, body, and analytical context.

## Anti-Corruption Layer

When integrating with external systems or receiving events, external or transport structures must not be transferred directly into Domain.

In Notification Service:

- the Redis Stream transport envelope is decoded by `AlertTriggeredV1Decoder` into the immutable application DTO `AlertTriggeredV1`;
- the `ConsumeAlertTriggered` Application use case converts the DTO into the local `Notification` domain entity and a `ConsumedEvent` deduplication record;
- the notification service's Domain layer knows nothing about Redis, Eloquent, or the analytics service's models.

## Compatibility and Versioning

API and event contract evolution follows a strict compatibility policy:

- **OpenAPI HTTP API:** versioned in the URL (`/api/v2/...` for Analytics, `/api/v1/...` for Notification). Protected Analytics v1 endpoints return `410 Gone`, requiring Sanctum Bearer authentication through v2.
- **Event Contracts:** the `alert.triggered.v1` schema (`contracts/events/alert-triggered.v1.schema.json`) is immutable after publication (`immutable`).
- Any semantic change or addition of required fields requires a new event version (for example, `alert.triggered.v2`) with a separate decoder and handler. Unsupported event versions are sent to the dead-letter stream and do not crash the consumer group.

## API Authorization and Capability Checks (Workspace RBAC)

Access to analytics service resources is controlled at the capability level:

1. **OpenAPI contract (`contracts/openapi/analytics-v2.yaml`):**
   - All protected endpoints are annotated with the `x-required-capability` extension, specifying the atomic permission required for the operation:
     - `analytics.view`: analytical reports and summaries (sales, inventory, ABC/XYZ, suppliers);
     - `dashboards.view`: reading dashboard and saved view lists and configurations;
     - `dashboards.manage`: creating, updating, and deleting dashboards and views;
     - `imports.view`: reading data import batches and statuses;
     - `imports.manage`: uploading files and retrying imports;
     - `alerts.view`: reading alert rules, active incidents, and summaries;
     - `alerts.manage`: creating, editing, deleting, and toggling rules, manually triggering evaluation, acknowledging and resolving alerts;
     - `workspace.members.manage`: viewing workspace members and changing their roles;
     - `workspace.settings.manage`: renaming the workspace (owner only).
   - The `WorkspaceResponse` schema includes a required `capabilities: string[]` array, calculated by the backend from the current user's role in the requested workspace.
2. **Backend Enforcement:**
   - `RequireWorkspaceCapabilityMiddleware` validates the tenant context and the caller's permission before passing control to controllers and service layers.
   - If the required capability is absent, a standardized response is returned:
     ```json
     {
       "error": "INSUFFICIENT_CAPABILITY",
       "message": "User does not have required capability '...'",
       "required_capability": "..."
     }
     ```
   - Race condition protection and the invariant requiring at least one owner are enforced by a transactional workspace lock; violations return `409 LAST_WORKSPACE_OWNER`.
   - Cross-workspace isolation is strictly checked before business operations execute.
