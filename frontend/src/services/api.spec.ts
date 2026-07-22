import { describe, expect, it } from 'vitest'
import type { AxiosError } from 'axios'

import { parseErrorEnvelope } from './api'

function axiosError(
  data: unknown,
  status = 422,
  headers: Record<string, string> = { 'x-request-id': 'hdr-1' },
): AxiosError {
  return {
    isAxiosError: true,
    name: 'AxiosError',
    message: 'Request failed',
    toJSON: () => ({}),
    response: {
      data,
      status,
      statusText: 'Unprocessable',
      headers,
      config: {} as never,
    },
  } as AxiosError
}

describe('parseErrorEnvelope', () => {
  it('reads nested error envelope from ApiErrorRenderer', () => {
    const err = parseErrorEnvelope(
      axiosError({
        error: {
          code: 'validation_error',
          message: 'The request is invalid.',
          details: { email: ['Required'] },
          request_id: 'req-99',
        },
      }),
    )

    expect(err.message).toBe('The request is invalid.')
    expect(err.code).toBe('validation_error')
    expect(err.errors).toEqual({ email: ['Required'] })
    expect(err.requestId).toBe('req-99')
    expect(err.status).toBe(422)
  })

  it('falls back to header request id when body omits it', () => {
    const err = parseErrorEnvelope(
      axiosError({
        error: {
          code: 'forbidden',
          message: 'Nope',
        },
      }, 403),
    )

    expect(err.requestId).toBe('hdr-1')
    expect(err.message).toBe('Nope')
  })

  it('maps 429 to a localized too-many-requests message', () => {
    const err = parseErrorEnvelope(
      axiosError({
        error: {
          code: 'too_many_requests',
          message: 'Slow down',
        },
      }, 429),
    )

    expect(err.status).toBe(429)
    expect(err.code).toBe('too_many_requests')
    expect(err.message).toBe(
      'Too many requests. Please wait a moment and try again.',
    )
  })

  it('includes Retry-After seconds in the localized 429 message', () => {
    const err = parseErrorEnvelope(
      axiosError(
        {
          error: {
            code: 'too_many_requests',
            message: 'Slow down',
          },
        },
        429,
        { 'retry-after': '12', 'x-request-id': 'hdr-1' },
      ),
    )

    expect(err.retryAfterSeconds).toBe(12)
    expect(err.message).toBe(
      'Too many requests. Please wait 12s and try again.',
    )
  })

  it('maps too_many_requests code even when status is not 429', () => {
    const err = parseErrorEnvelope(
      axiosError({
        error: {
          code: 'too_many_requests',
          message: 'Slow down',
        },
      }, 400),
    )

    expect(err.message).toBe(
      'Too many requests. Please wait a moment and try again.',
    )
  })
})
