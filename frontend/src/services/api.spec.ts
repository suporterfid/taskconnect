import { describe, expect, it } from 'vitest'
import type { AxiosError } from 'axios'

import { parseErrorEnvelope } from './api'

function axiosError(data: unknown, status = 422): AxiosError {
  return {
    isAxiosError: true,
    name: 'AxiosError',
    message: 'Request failed',
    toJSON: () => ({}),
    response: {
      data,
      status,
      statusText: 'Unprocessable',
      headers: { 'x-request-id': 'hdr-1' },
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
})
