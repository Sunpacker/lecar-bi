# 00. Overview

## Project Purpose

AutoBI is a BI platform for analyzing sales, inventory, suppliers, and related automotive e-commerce processes.

The system must allow users to access summary and detailed analytics, work with interactive dashboards, apply filters, investigate the causes of metric changes, and eventually use automated notifications and forecasting.

## Main Architectural Goals

The architecture must address two goals at the same time:

- remain simple enough to develop the first version;
- avoid creating constraints on subsequently splitting the system into additional microservices.

## Core Principles

The project establishes the following principles:

- a monorepo for unified codebase management;
- independently deployable services within the monorepo;
- Next.js is responsible for the user interface and frontend layer;
- Laravel runs as a separate backend microservice;
- Laravel follows DDD with clear bounded context boundaries;
- business logic must not reside in the frontend;
- each microservice must own its data;
- services integrate through public contracts and events;
- analytical queries may use specialized read models;
- new microservices are extracted only when there is a real architectural reason;
- the architecture must support independent testing, building, and deployment of services.

## Architectural Style

The initial version is a distributed system with two services:

- a Next.js web service;
- a Laravel analytics service.

Laravel is a single deployable service, internally divided into domain modules.

This approach starts with moderate complexity while preserving the option to extract ingestion, notification, forecasting, identity, and other services later.
