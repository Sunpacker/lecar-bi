# 04. Backend Architecture — Laravel DDD

## Роль Laravel

Laravel является отдельным analytics-микросервисом.

Он представляет собой единый deployable-сервис, внутри которого используются DDD и модульное разделение по bounded contexts.

## Базовая структура модуля

Каждый bounded context должен иметь собственные слои:

- Domain;
- Application;
- Infrastructure;
- Presentation.

Предпочтительный принцип организации: сначала модуль, затем его внутренние слои.

Это упрощает дальнейшее выделение bounded context в отдельный сервис.

## Domain Layer

Domain Layer содержит бизнес-модель и не должен зависеть от Laravel.

В Domain Layer располагаются:

- entities;
- aggregates;
- value objects;
- domain services;
- domain events;
- repository interfaces;
- domain exceptions;
- specifications и иные доменные политики.

Domain Layer не должен знать о:

- HTTP;
- Eloquent;
- PostgreSQL;
- Redis;
- Laravel controllers;
- framework helpers;
- очередях;
- UI.

## Application Layer

Application Layer описывает сценарии использования системы.

Он отвечает за:

- команды;
- запросы;
- application services;
- handlers;
- DTO;
- orchestration;
- транзакционные use cases;
- взаимодействие с domain через публичные интерфейсы.

Application Layer координирует работу, но не должен превращаться в место хранения бизнес-правил.

## Infrastructure Layer

Infrastructure Layer реализует технические детали:

- persistence;
- Eloquent;
- repository implementations;
- cache;
- queues;
- integrations;
- import;
- technical adapters;
- framework bindings.

Infrastructure зависит от внутренних слоёв, а не наоборот.

## Presentation Layer

Presentation Layer является внешней границей HTTP.

Он отвечает за:

- controllers;
- requests;
- resources;
- route registration;
- преобразование HTTP-запроса в application use case;
- преобразование результата use case в HTTP-ответ.

Controllers должны оставаться тонкими.

## Направление зависимостей

Главный архитектурный принцип:

- Presentation зависит от Application;
- Application зависит от Domain;
- Infrastructure реализует контракты, необходимые Domain и Application;
- Domain не зависит от Infrastructure.

## Laravel как framework

Laravel используется как техническая платформа, а не как доменная модель.

Framework должен окружать domain, а не проникать в него.
