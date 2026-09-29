# Phase 0 — Repository Bootstrap

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Create a stable foundation for the monorepo.

## Functionality

- root monorepo structure;
- Next.js in a dedicated application root;
- Laravel analytics service in a dedicated service root;
- `docs/architecture/`;
- shared contracts;
- infrastructure directory;
- environment conventions;
- root developer commands;
- README;
- `AGENTS.md`;
- `docs/roadmap/ROADMAP.md`.

## Exit Criteria

Frontend/backend have independent roots, documentation paths match `docs/architecture/`, ownership is unambiguous, and the root workflow is documented.

## Progress

- The Compose configuration accepts environment parameters for images, host ports, public URLs, and application settings; public Next.js URLs are also passed at build time.
- Backend and PostgreSQL secrets are passed through `infra/.env`; the template is stored in `infra/.env.example`.
- Root commands load `infra/.env` and fall back to local values from `infra/.env.example` when it is absent.
- The remaining bootstrap exit criteria still need to be verified before closing the phase.
