export interface LogEntryPayload {
  requestId?: string | null
  correlationId?: string | null
  operation?: string | null
  errorCode?: string | null
  context?: Record<string, unknown>
}

const SENSITIVE_KEY_REGEX =
  /^(.*_)?(password|token|secret|authorization|auth|cookie|credential)(_.*)?$|^(.*_)?(api_?key|secret_?key|private_?key|auth_?key|access_?key|^key)$/i

function sanitize(data: unknown): unknown {
  if (data === null || typeof data !== 'object') {
    return data
  }
  if (Array.isArray(data)) {
    return data.map(sanitize)
  }
  const result: Record<string, unknown> = {}
  for (const [key, value] of Object.entries(data as Record<string, unknown>)) {
    if (SENSITIVE_KEY_REGEX.test(key)) {
      result[key] = '[REDACTED]'
    } else {
      result[key] = sanitize(value)
    }
  }
  return result
}

export function formatLogEntry(
  level: 'debug' | 'info' | 'warn' | 'error',
  message: string,
  payload?: LogEntryPayload,
) {
  return {
    timestamp: new Date().toISOString(),
    level: level.toUpperCase(),
    service: 'web',
    environment: process.env.NODE_ENV ?? 'development',
    operation: payload?.operation ?? null,
    request_id: payload?.requestId ?? null,
    correlation_id: payload?.correlationId ?? payload?.requestId ?? null,
    event_id: null,
    job_id: null,
    error_code: payload?.errorCode ?? null,
    message,
    context: sanitize(payload?.context ?? {}) as Record<string, unknown>,
  }
}

export function logInfo(message: string, payload?: LogEntryPayload): void {
  console.log(JSON.stringify(formatLogEntry('info', message, payload)))
}

export function logWarn(message: string, payload?: LogEntryPayload): void {
  console.warn(JSON.stringify(formatLogEntry('warn', message, payload)))
}

export function logError(message: string, payload?: LogEntryPayload): void {
  console.error(JSON.stringify(formatLogEntry('error', message, payload)))
}
