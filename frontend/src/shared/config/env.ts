import { getBackendApiUrl } from './backend-url'

export const env = {
  appUrl:
    process.env.NEXT_PUBLIC_APP_URL ??
    (process.env.VERCEL_URL
      ? `https://${process.env.VERCEL_URL}`
      : 'http://localhost:3000'),
  analyticsApiUrl: getBackendApiUrl(),
} as const
