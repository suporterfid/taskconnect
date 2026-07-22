import axios, {
  type AxiosError,
  type AxiosInstance,
  type InternalAxiosRequestConfig,
} from 'axios'

import { i18n } from '@/i18n'
import type { ApiErrorBody, ApiErrorEnvelope } from './types'

export class ApiError extends Error {
  readonly status: number
  readonly code?: string
  readonly errors?: Record<string, string[]>
  readonly requestId?: string
  readonly retryAfterSeconds?: number

  constructor(
    message: string,
    status: number,
    options?: {
      code?: string
      errors?: Record<string, string[]>
      requestId?: string
      retryAfterSeconds?: number
    },
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = options?.code
    this.errors = options?.errors
    this.requestId = options?.requestId
    this.retryAfterSeconds = options?.retryAfterSeconds
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

function asStringRecord(value: unknown): Record<string, string[]> | undefined {
  if (!isRecord(value)) {
    return undefined
  }
  const entries = Object.entries(value)
  if (entries.length === 0) {
    return undefined
  }
  const result: Record<string, string[]> = {}
  for (const [key, raw] of entries) {
    if (Array.isArray(raw) && raw.every((item) => typeof item === 'string')) {
      result[key] = raw
    }
  }
  return Object.keys(result).length > 0 ? result : undefined
}

/** Parse `{ error: { code, message, details, request_id } }` from ApiErrorRenderer. */
export function parseErrorEnvelope(error: AxiosError): ApiError {
  const status = error.response?.status ?? 0
  const data = error.response?.data
  const headers = error.response?.headers ?? {}
  const headerRequestId =
    typeof headers['x-request-id'] === 'string' ? headers['x-request-id'] : undefined
  const retryAfterSeconds = parseRetryAfterSeconds(headers['retry-after'])

  if (isRecord(data) && isRecord(data.error)) {
    const body = data.error as unknown as ApiErrorBody
    const code = typeof body.code === 'string' ? body.code : undefined
    const tooManyRequests =
      status === 429 || code === 'too_many_requests'
    return new ApiError(
      tooManyRequests
        ? tooManyRequestsMessage(retryAfterSeconds)
        : typeof body.message === 'string' && body.message
          ? body.message
          : i18n.global.t('common.errors.requestFailed'),
      status,
      {
        code,
        errors: asStringRecord(body.details),
        requestId:
          typeof body.request_id === 'string'
            ? body.request_id
            : headerRequestId,
        retryAfterSeconds,
      },
    )
  }

  // Legacy / unexpected flat shapes
  if (isRecord(data) && typeof data.message === 'string') {
    const code = typeof data.code === 'string' ? data.code : undefined
    const tooManyRequests =
      status === 429 || code === 'too_many_requests'
    return new ApiError(
      tooManyRequests
        ? tooManyRequestsMessage(retryAfterSeconds)
        : data.message,
      status,
      {
        code,
        errors: asStringRecord(data.errors ?? data.details),
        requestId:
          (typeof data.request_id === 'string' ? data.request_id : undefined) ??
          headerRequestId,
        retryAfterSeconds,
      },
    )
  }

  if (status === 429) {
    return new ApiError(tooManyRequestsMessage(retryAfterSeconds), status, {
      requestId: headerRequestId,
      retryAfterSeconds,
    })
  }

  return new ApiError(error.message || i18n.global.t('common.errors.requestFailed'), status, {
    requestId: headerRequestId,
    retryAfterSeconds,
  })
}

function parseRetryAfterSeconds(raw: unknown): number | undefined {
  if (typeof raw === 'number' && Number.isFinite(raw) && raw >= 0) {
    return Math.ceil(raw)
  }
  if (typeof raw !== 'string' || raw.trim() === '') {
    return undefined
  }
  const asInt = Number.parseInt(raw, 10)
  if (!Number.isNaN(asInt) && String(asInt) === raw.trim()) {
    return Math.max(0, asInt)
  }
  const asDate = Date.parse(raw)
  if (!Number.isNaN(asDate)) {
    return Math.max(0, Math.ceil((asDate - Date.now()) / 1000))
  }
  return undefined
}

function tooManyRequestsMessage(retryAfterSeconds?: number): string {
  if (retryAfterSeconds != null && retryAfterSeconds > 0) {
    return i18n.global.t('common.errors.tooManyRequestsRetryAfter', {
      seconds: retryAfterSeconds,
    })
  }
  return i18n.global.t('common.errors.tooManyRequests')
}

let csrfInitialized = false

export async function ensureCsrfCookie(): Promise<void> {
  if (csrfInitialized) {
    return
  }

  // Must hit /sanctum/csrf-cookie at the site root — not under /api/v1
  // (axios would otherwise combine baseURL + path into /api/v1/sanctum/...).
  await axios.get('/sanctum/csrf-cookie', {
    withCredentials: true,
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
  csrfInitialized = true
}

/** Force the next auth call to refresh the CSRF cookie (e.g. after logout). */
export function resetCsrfCookie(): void {
  csrfInitialized = false
}

export const api: AxiosInstance = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const requestId = crypto.randomUUID()
  config.headers.set('X-Request-Id', requestId)
  return config
})

api.interceptors.response.use(
  (response) => {
    const requestId = response.headers['x-request-id']
    if (requestId) {
      response.config.headers.set('X-Response-Request-Id', requestId)
    }
    return response
  },
  (error: AxiosError) => Promise.reject(parseErrorEnvelope(error)),
)

export default api

// Re-export for type-only consumers that imported ApiErrorEnvelope from api path historically.
export type { ApiErrorEnvelope }
