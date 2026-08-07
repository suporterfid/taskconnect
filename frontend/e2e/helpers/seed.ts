import { request as pwRequest, type APIRequestContext } from '@playwright/test'

const PIPELINE_TEMPLATE = 'convert-index-publish'

function statefulHeaders(baseURL: string): Record<string, string> {
  return {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    Origin: new URL(baseURL).origin,
    Referer: `${new URL(baseURL).origin}/`,
  }
}

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
    extraHTTPHeaders: statefulHeaders(baseURL),
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

/**
 * Auto-seed a plain active task plus one run-now'd run, so `a11y.spec.ts` (#98) can
 * inspect a real task detail and run detail page instead of skipping. No-ops and
 * returns null when E2E_EMAIL / E2E_PASSWORD are unset.
 */
export async function seedTaskAndRunFixtures(
  baseURL: string,
): Promise<{ taskId: string; runId: string | null } | null> {
  const email = process.env.E2E_EMAIL
  const password = process.env.E2E_PASSWORD
  if (!email || !password) {
    return null
  }

  const ctx = await pwRequest.newContext({
    baseURL,
    extraHTTPHeaders: statefulHeaders(baseURL),
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

    const taskRes = await ctx.post(`${base}/tasks`, {
      headers: {
        'X-XSRF-TOKEN': xsrf,
        'Idempotency-Key': `e2e-a11y-task-${Date.now()}-${Math.random().toString(36).slice(2)}`,
      },
      data: {
        name: 'E2E a11y fixture task',
        method: 'POST',
        url_or_path: 'http://e2e-fixture.invalid/hook',
        content_type: 'application/json',
        timezone: 'UTC',
        schedule: { kind: 'daily_at', time: '09:00', timezone: 'UTC' },
        retry_policy: { max_attempts: 3, strategy: 'standard_exponential' },
        definition_status: 'active',
      },
    })
    if (!taskRes.ok()) {
      throw new Error(`E2E fixture seed: task create failed (${taskRes.status()}): ${await taskRes.text()}`)
    }
    const taskId = ((await taskRes.json()).data as { id: string }).id

    const runRes = await ctx.post(`${base}/tasks/${taskId}/run-now`, {
      headers: {
        'X-XSRF-TOKEN': xsrf,
        'Idempotency-Key': `e2e-a11y-run-${Date.now()}-${Math.random().toString(36).slice(2)}`,
      },
    })
    const runId = runRes.ok() ? (((await runRes.json()).data as { id?: string })?.id ?? null) : null

    return { taskId, runId }
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
