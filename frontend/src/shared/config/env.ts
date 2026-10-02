export const env = {
  appUrl:
    process.env.NEXT_PUBLIC_APP_URL ??
    (process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : 'http://localhost:3000'),
  analyticsApiUrl:
    process.env.ANALYTICS_INTERNAL_URL ??
    process.env.NEXT_PUBLIC_ANALYTICS_API_URL ??
    (process.env.VERCEL
      ? 'https://api.veloza.ru/lecar-bi/api/v1'
      : 'http://localhost:8080/api/v1'),
} as const
