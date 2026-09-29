# Phase 0 — Bootstrap репозитория

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Создать стабильную основу монорепозитория.

## Функциональность

- корневая monorepo-структура;
- Next.js в выделенном корне приложения;
- Laravel analytics service в выделенном корне сервиса;
- `docs/architecture/`;
- общие contracts;
- infrastructure directory;
- environment conventions;
- root developer commands;
- README;
- `AGENTS.md`;
- `docs/roadmap/ROADMAP.md`.

## Exit Criteria

Frontend/backend имеют независимые корни, пути документации соответствуют `docs/architecture/`, ownership однозначен, root workflow документирован.

## Прогресс

- Compose-конфигурация принимает параметры окружения для образов, host-портов, публичных URL и настроек приложений; публичные Next.js URL также передаются на этапе сборки.
- Секреты backend и PostgreSQL передаются через `infra/.env`; шаблон хранится в `infra/.env.example`.
- Root-команды загружают `infra/.env`, а при его отсутствии используют локальные значения из `infra/.env.example`.
- Остаётся проверить остальные exit criteria bootstrap-фазы перед её закрытием.
