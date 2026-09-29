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

`api:generate` refreshes `src/shared/api/generated/schema.ts` from `contracts/openapi/analytics-v2.yaml`. Generated types are consumed through the typed client in `src/shared/api/analytics-client.ts`.

## Storybook

Компоненты интерфейса и готовые композиции можно посмотреть без backend и авторизации:

```bash
npm run storybook             # http://localhost:6006
npm run test:storybook        # Playwright Chromium
npm run build-storybook       # storybook-static/
```

Для браузерных тестов один раз установите Chromium: `npx playwright install chromium`. В toolbar Storybook есть переключение светлой и тёмной тем. Исходники общих композиций и публичный импорт находятся в `src/shared/ui/`; истории базовых примитивов лежат рядом с ними в `components/ui/`.
