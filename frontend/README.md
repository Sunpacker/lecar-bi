# AutoBI Frontend

Independent Next.js service. It contains only UI, routing, frontend state and the typed API-client boundary.

```bash
npm ci
npm run dev
```

Default URL: `http://localhost:3000`.

From the repository root, `make dev` starts the complete Docker development environment with Fast Refresh enabled. Use `make dev-frontend` to run only Next.js on the host.

Quality and contract commands:

```bash
npm run contracts:validate
npm run api:generate
npm run lint
npm run format:check
npm run typecheck
npm test
npm run build
```

`api:generate` refreshes `src/shared/api/generated/schema.ts` from `contracts/openapi/analytics-v1.yaml`. Generated types are consumed through the typed client in `src/shared/api/analytics-client.ts`.
