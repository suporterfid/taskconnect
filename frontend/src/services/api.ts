import axios, {
  type AxiosError,
  type AxiosInstance,
  type InternalAxiosRequestConfig,
} from 'axios'

import type { ApiErrorEnvelope } from './types'

export class ApiError extends Error {
  readonly status: number
  readonly code?: string
  readonly errors?: Record<string, string[]>
  readonly requestId?: string

  constructor(
    message: string,
    status: number,
    options?: {
      code?: string
      errors?: Record<string, string[]>
      requestId?: string
    },
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = options?.code
    this.errors = options?.errors
    this.requestId = options?.requestId
  }
}

function parseErrorEnvelope(error: AxiosError): ApiError {
  const status = error.response?.status ?? 0
  const data = error.response?.data
  const requestId =
    error.response?.headers['x-request-id'] ??
    (typeof data === 'object' && data !== null && 'request_id' in data
      ? String((data as ApiErrorEnvelope).request_id)
      : undefined)

  if (typeof data === 'object' && data !== null && 'message' in data) {
    const envelope = data as ApiErrorEnvelope
    return new ApiError(envelope.message, status, {
      code: envelope.code,
      errors: envelope.errors,
      requestId: envelope.request_id ?? requestId,
    })
  }

  return new ApiError(error.message || 'Request failed', status, { requestId })
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
