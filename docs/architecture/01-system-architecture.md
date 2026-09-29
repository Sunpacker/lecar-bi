# 01. System Architecture

## Main Components

The system consists of several logical layers:

- the user's browser;
- the Next.js web service;
- the Laravel analytics backend service;
- the Laravel notification microservice;
- PostgreSQL: independent stores for analytics and notification;
- Redis for integration event transport (Redis Streams), caching, queues, and locks;
- future external or internal microservices.

## Next.js Responsibilities

Next.js is responsible for:

- the user interface;
- routing;
- server-side and client-side rendering;
- interactive dashboards;
- tables, filters, and visualizations;
- frontend-specific state;
- user session handling;
- backend API calls;
- acting as a thin BFF when needed.

Next.js must not become a place for analytics business rules.

## Laravel Responsibilities

Laravel is responsible for:

- business rules;
- calculating analytical metrics;
- working with domain entities and value objects;
- orchestrating use cases;
- data storage;
- building read models;
- data import;
- background jobs;
- integration events;
- alerting rules;
- providing an API for the frontend and other services.

## Notification Service Responsibilities

Notification Service is responsible for:

- asynchronously consuming `alert.triggered.v1` events from Redis Stream;
- event idempotency and deduplication (`consumed_events`);
- creating and storing notification projections (`notifications`) in its own database;
- providing isolated technical health/readiness endpoints;
- isolation from analytics: no access to analytics PostgreSQL and no direct synchronous calls.

## Service Boundaries

Services communicate through explicit contracts:

- Next.js and Analytics: an OpenAPI HTTP contract.
- Analytics and Notification: an asynchronous event contract over Redis Stream (Transactional Outbox in analytics, Consumer Group in notification).
- The frontend does not depend on the internal structure of backend models or data storage details.
- The notification service does not depend on the internal structure of the analytics domain or its database.

## Independence of Deployable Services

Next.js, Analytics, and Notification must:

- have independent build processes;
- have independent Docker images;
- have independent environment configurations;
- support separate deployment;
- own their databases (database per service);
- communicate only through agreed contracts.

## System Evolution

As the project grows, individual domain or infrastructure areas may be extracted into standalone services.

Extraction must happen only for a reason, such as:

- a distinct load profile;
- a separate team;
- an independent lifecycle;
- a specific technology;
- a clear business boundary;
- the need for a separate SLA.
