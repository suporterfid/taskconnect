import { request as pwRequest, type APIRequestContext } from '@playwright/test'

const PIPELINE_TEMPLATE = 'convert-index-publish'

/**
 * Auto-seed a dead run (DLQ) and a pipeline instance for the E2E operator's default
 * workspace, so `dlq-pipelines.spec.ts` can assert inspect/replay and pipeline detail
 * instead of skipping. No-ops when E2E_EMAIL / E2E_PASSWORD are unset.
 *
 * Mirrors the SPA's own workspace selection (frontend/src/stores/tenant.ts): first
 * tenant, first non-archived environment.
 */
export async function seedDlqAndPipelineFixtures(baseURL: string): Promise<void> {
  const email = process.env.E2E_EMAIL
  const password = process.env.E2E_PASSWORD
  if (!email || !password) {
    return
  }

  const ctx = await pwRequest.newContext({
    baseURL,
    extraHTTPHeaders: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  try {
    await ctx.get('/sanctum/csrf-cookie')

    const login = await ctx.post('/api/v1/auth/login', {
      data: { email, password },
      headers: { 'X-XSRF-TOKEN': await xsrfToken(ctx) },
    })
    if (!login.ok()) {
      throw new Error(`E2E fixture seed: login failed (${login.status()}): ${await login.text()}`)
    }

    const xsrf = await xsrfToken(ctx)

    const tenantsRes = await ctx.get('/api/v1/tenants', { headers: { 'X-XSRF-TOKEN': xsrf } })
    const tenants = ((await tenantsRes.json()).data ?? []) as Array<{ id: string }>
    const tenantId = tenants[0]?.id
    if (!tenantId) {
      throw new Error('E2E fixture seed: E2E operator has no tenant')
    }

    const envsRes = await ctx.get(`/api/v1/tenants/${tenantId}/environments`, {
      headers: { 'X-XSRF-TOKEN': xsrf },
    })
    const environments = ((await envsRes.json()).data ?? []) as Array<{
      id: string
      archived_at: string | null
    }>
    const environmentId = environments.find((e) => !e.archived_at)?.id ?? environments[0]?.id
    if (!environmentId) {
      throw new Error('E2E fixture seed: E2E operator tenant has no environment')
    }

    const base = `/api/v1/tenants/${tenantId}/environments/${environmentId}`

    const dlqSeed = await ctx.post(`${base}/e2e/dlq-fixture`, {
      headers: { 'X-XSRF-TOKEN': xsrf },
    })
    if (!dlqSeed.ok()) {
      throw new Error(
        `E2E fixture seed: dead-run seed failed (${dlqSeed.status()}) — is the app running ` +
          'with APP_ENV=local or testing? See docs/deployment/e2e-operator.md.',
      )
    }

    const pipelineSeed = await ctx.post(`${base}/pipelines/${PIPELINE_TEMPLATE}/instances`, {
      headers: {
        'X-XSRF-TOKEN': xsrf,
        'Idempotency-Key': `e2e-fixture-${Date.now()}-${Math.random().toString(36).slice(2)}`,
      },
      data: {
        nodes: {
          convert: {
            method: 'POST',
            url_or_path: 'http://e2e-fixture.invalid/convert',
            body: { file_id: 'e2e-fixture' },
          },
          index: {
            method: 'POST',
            url_or_path: 'http://e2e-fixture.invalid/index',
            body: { doc_id: 'e2e-fixture' },
          },
          publish: {
            method: 'POST',
            url_or_path: 'http://e2e-fixture.invalid/publish',
          },
        },
      },
    })
    if (!pipelineSeed.ok()) {
      throw new Error(
        `E2E fixture seed: pipeline instance seed failed (${pipelineSeed.status()}): ${await pipelineSeed.text()}`,
      )
    }
  } finally {
    await ctx.dispose()
  }
}

async function xsrfToken(ctx: APIRequestContext): Promise<string> {
  const { cookies } = await ctx.storageState()
  const cookie = cookies.find((c) => c.name === 'XSRF-TOKEN')
  if (!cookie) {
    throw new Error('E2E fixture seed: XSRF-TOKEN cookie missing after /sanctum/csrf-cookie')
  }
  return decodeURIComponent(cookie.value)
}
