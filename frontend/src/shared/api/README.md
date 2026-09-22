# Typed API client boundary

`generated/schema.ts` создаётся командой `npm run api:generate` из
`contracts/openapi/analytics-v1.yaml`. `analytics-client.ts` использует эти типы через
`openapi-fetch`. Ручные доменные типы frontend здесь не размещаются.
