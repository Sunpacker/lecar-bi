export const env = {
  appUrl: process.env.NEXT_PUBLIC_APP_URL ?? 'http://localhost:3000',
  analyticsApiUrl:
    process.env.ANALYTICS_INTERNAL_URL ??
    process.env.NEXT_PUBLIC_ANALYTICS_API_URL ??
    'http://localhost:8080/api/v1',
} as const
