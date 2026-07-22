import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockRejectedValue(
      Object.assign(new Error('Unauthorized'), { status: 401 }),
    ),
    post: vi.fn(),
    patch: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
  ApiError: class ApiError extends Error {
    status: number
    constructor(message: string, status: number) {
      super(message)
      this.status = status
    }
  },
  ensureCsrfCookie: vi.fn(),
}))

describe('router', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    const router = (await import('@/router')).default
    await router.push('/')
    await router.isReady()
  })

  it('defines login route', async () => {
    const router = (await import('@/router')).default
    const login = router.getRoutes().find((r) => r.name === 'login')
    expect(login).toBeDefined()
    expect(login?.path).toBe('/login')
  })

  it('defines guest password reset routes', async () => {
    const router = (await import('@/router')).default
    expect(router.getRoutes().find((r) => r.name === 'forgot-password')?.path).toBe(
      '/forgot-password',
    )
    expect(router.getRoutes().find((r) => r.name === 'reset-password')?.path).toBe(
      '/reset-password',
    )
  })

  it('defines secrets route under authenticated layout', async () => {
    const router = (await import('@/router')).default
    const secrets = router.getRoutes().find((r) => r.name === 'secrets')
    expect(secrets).toBeDefined()
    expect(secrets?.path).toBe('/secrets')
  })

  it('defines dashboard route under authenticated layout', async () => {
    const router = (await import('@/router')).default
    const dashboard = router.getRoutes().find((r) => r.name === 'dashboard')
    expect(dashboard).toBeDefined()
    expect(dashboard?.path).toBe('/dashboard')
  })

  it('defines dlq and pipelines routes under authenticated layout', async () => {
    const router = (await import('@/router')).default
    expect(router.getRoutes().find((r) => r.name === 'dlq')?.path).toBe('/dlq')
    expect(router.getRoutes().find((r) => r.name === 'pipelines')?.path).toBe('/pipelines')
    expect(router.getRoutes().find((r) => r.name === 'pipelines-detail')?.path).toBe(
      '/pipelines/:templateName/instances/:id',
    )
  })

  it('redirects unauthenticated users to login', async () => {
    const router = (await import('@/router')).default
    await router.push('/dashboard')
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirects unauthenticated dlq and pipelines visits to login', async () => {
    const router = (await import('@/router')).default
    await router.push('/dlq')
    expect(router.currentRoute.value.name).toBe('login')
    await router.push('/pipelines')
    expect(router.currentRoute.value.name).toBe('login')
  })
})
