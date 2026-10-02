/**
 * Resolves the backend API base URL for server-side requests (BFF, SSR, proxy).
 *
 * Rules:
 * 1. If running in Vercel or production:
 *    - Reject any value containing 'localhost' or '127.0.0.1' (common copy-paste pitfall).
 *    - Check process.env.ANALYTICS_INTERNAL_URL first.
 *    - If missing or invalid, check process.env.NEXT_PUBLIC_ANALYTICS_API_URL (if not localhost).
 *    - If still missing or invalid, fallback to 'https://api.veloza.ru/lecar-bi/api/v1'.
 * 2. If running locally (dev/test):
 *    - Use ANALYTICS_INTERNAL_URL || NEXT_PUBLIC_ANALYTICS_API_URL || 'http://localhost:8080/api/v1'.
 * 3. Normalization:
 *    - Trim leading/trailing whitespace.
 *    - Strip trailing slashes.
 *    - If URL points to https://api.veloza.ru without /lecar-bi or /api/v1, ensure it targets /lecar-bi/api/v1.
 *    - If URL does not end with /api/v1, append /api/v1.
 */
export function getBackendApiUrl(): string {
  const isVercel = Boolean(process.env.VERCEL)
  const isProd = process.env.NODE_ENV === 'production'

  let raw = process.env.ANALYTICS_INTERNAL_URL

  // If ANALYTICS_INTERNAL_URL is not set or invalid on cloud/production
  if (!raw || ((isVercel || isProd) && isLocalhost(raw))) {
    const publicUrl = process.env.NEXT_PUBLIC_ANALYTICS_API_URL
    if (publicUrl && (!isVercel || !isLocalhost(publicUrl))) {
      raw = publicUrl
    } else if (isVercel || isProd) {
      raw = 'https://api.veloza.ru/lecar-bi/api/v1'
    } else {
      raw = 'http://localhost:8080/api/v1'
    }
  }

  return normalizeBackendUrl(raw)
}

export function isLocalhost(url: string): boolean {
  return (
    url.includes('localhost') ||
    url.includes('127.0.0.1') ||
    url.includes('0.0.0.0') ||
    url.includes('[::1]')
  )
}

export function normalizeBackendUrl(url: string): string {
  let cleaned = url.trim().replace(/\/+$/, '')

  // Specific handling for veloza.ru
  if (cleaned === 'https://api.veloza.ru' || cleaned === 'http://api.veloza.ru') {
    return `${cleaned}/lecar-bi/api/v1`
  }
  if (cleaned.endsWith('/lecar-bi')) {
    return `${cleaned}/api/v1`
  }
  if (cleaned.endsWith('/autobi')) {
    return `${cleaned}/api/v1`
  }

  // Ensure /api/v1 suffix
  if (!cleaned.endsWith('/api/v1')) {
    if (cleaned.endsWith('/api')) {
      return `${cleaned}/v1`
    }
    // Only append if it doesn't already have another api version like /api/v2
    if (!cleaned.includes('/api/')) {
      return `${cleaned}/api/v1`
    }
  }

  return cleaned
}
