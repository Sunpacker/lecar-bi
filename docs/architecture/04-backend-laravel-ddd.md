# 04. Backend Architecture — Laravel DDD

## Laravel's Role

Laravel is a separate analytics microservice.

It is a single deployable service that internally uses DDD and modular separation by bounded context.

## Basic Module Structure

Each bounded context must have its own layers:

- Domain;
- Application;
- Infrastructure;
- Presentation.

The preferred organization principle is module first, then its internal layers.

This simplifies later extraction of a bounded context into a separate service.

## Domain Layer

The Domain Layer contains the business model and must not depend on Laravel.

The Domain Layer contains:

- entities;
- aggregates;
- value objects;
- domain services;
- domain events;
- repository interfaces;
- domain exceptions;
- specifications and other domain policies.

The Domain Layer must not know about:

- HTTP;
- Eloquent;
- PostgreSQL;
- Redis;
- Laravel controllers;
- framework helpers;
- queues;
- UI.

## Application Layer

The Application Layer describes the system's use cases.

It is responsible for:

- commands;
- queries;
- application services;
- handlers;
- DTOs;
- orchestration;
- transactional use cases;
- interacting with the domain through public interfaces.

The Application Layer coordinates work but must not become a place for business rules.

## Infrastructure Layer

The Infrastructure Layer implements technical details:

- persistence;
- Eloquent;
- repository implementations;
- cache;
- queues;
- integrations;
- import;
- technical adapters;
- framework bindings.

Infrastructure depends on the inner layers, not the other way around.

## Presentation Layer

The Presentation Layer is the external HTTP boundary.

It is responsible for:

- controllers;
- requests;
- resources;
- route registration;
- converting an HTTP request into an application use case;
- converting a use case result into an HTTP response.

Controllers must remain thin.

## Dependency Direction

The core architectural principle:

- Presentation depends on Application;
- Application depends on Domain;
- Infrastructure implements the contracts required by Domain and Application;
- Domain does not depend on Infrastructure.

## Laravel as a Framework

Laravel is used as a technical platform, not as the domain model.

The framework must surround the domain rather than penetrate it.
