const REQUEST_ID_REGEX = /^[a-zA-Z0-9_\-.]{1,64}$/

export function sanitizeOrGenerateRequestId(headerValue?: string | null): string {
  if (headerValue && REQUEST_ID_REGEX.test(headerValue.trim())) {
    return headerValue.trim()
  }
  return crypto.randomUUID()
}
