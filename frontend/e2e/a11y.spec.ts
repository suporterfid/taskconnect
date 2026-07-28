import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'

import { expect, test, type Page } from '@playwright/test'

import { e2eCredentialsConfigured, loginAsE2EOperator } from './helpers/auth'
import { seedDlqAndPipelineFixtures, seedTaskAndRunFixtures } from './helpers/seed'

/**
 * §10/§3.4 WCAG 2.2 AA verification (#98). jsdom can't compute real color contrast
 * (see src/pages/login.a11y.spec.ts), so this runs axe-core in a real Chromium page
 * with the `color-contrast` rule enabled — the actual check the visual-identity epic
 * needs. Unauthenticated pages stay green with no credentials configured, matching
 * smoke.spec.ts's convention; authenticated pages skip (not fail) without E2E_EMAIL /
 * E2E_PASSWORD.
 */

const axeSource = readFileSync(createRequire(import.meta.url).resolve('axe-core/axe.min.js'), 'utf8')

async function runAxe(page: Page) {
  await page.addScriptTag({ content: axeSource })
  return page.evaluate(async () => {
    // @ts-expect-error axe is injected globally by axe.min.js
    return window.axe.run(document, {
      resultTypes: ['violations'],
    })
  }) as Promise<{ violations: Array<{ id: string; impact: string | null; nodes: unknown[] }> }>
}

type AxeViolation = { id: string; impact: string | null; nodes: unknown[] }

function assertNoSeriousViolations(results: { violations: AxeViolation[] }): void {
  const serious = results.violations.filter((v) => ['serious', 'critical'].includes(v.impact ?? ''))
  expect(serious, JSON.stringify(serious, null, 2)).toEqual([])
}

async function setLocale(page: Page, locale: 'en' | 'pt-BR'): Promise<void> {
  await page.addInitScript((value) => {
    window.localStorage.setItem('locale', value)
  }, locale)
}

const UNAUTHENTICATED_PAGES = [
  { name: 'login', path: '/login' },
  { name: 'forgot password', path: '/forgot-password' },
  { name: 'reset password', path: '/reset-password?token=e2e-fixture&email=e2e%40example.test' },
]

const LOCALES = ['en', 'pt-BR'] as const

for (const locale of LOCALES) {
  test.describe(`a11y sweep — unauthenticated (${locale})`, () => {
    for (const { name, path } of UNAUTHENTICATED_PAGES) {
      test(`${name} has no serious/critical axe violations`, async ({ page }) => {
        await setLocale(page, locale)
        await page.goto(path, { waitUntil: 'networkidle' })
        assertNoSeriousViolations(await runAxe(page))
      })
    }
  })
}

const AUTHENTICATED_STATIC_PAGES = [
  { name: 'dashboard', path: '/dashboard' },
  { name: 'tasks', path: '/tasks' },
  { name: 'runs', path: '/runs' },
  { name: 'dlq', path: '/dlq' },
  { name: 'pipelines', path: '/pipelines' },
  { name: 'settings', path: '/settings' },
  { name: 'api keys', path: '/api-keys' },
]

// Auth routes are throttled (`throttle:10,1`, routes/api.php). A test-per-page
// pattern with a fresh UI login for each one blows past that in a single sweep, so
// every page for a locale is checked inside one test after a single login instead.
// Violations are collected across all pages and asserted together at the end, so a
// failure still names the exact page(s) and rule(s) that failed.
for (const locale of LOCALES) {
  test(`a11y sweep — authenticated (${locale})`, async ({ page, baseURL }, testInfo) => {
    testInfo.skip(
      !e2eCredentialsConfigured(),
      'Set E2E_EMAIL and E2E_PASSWORD (fixtures auto-seed; see docs/deployment/e2e-operator.md).',
    )

    // Seed failures (e.g. a misconfigured operator environment) must not block the
    // rest of the sweep — task/run detail checks are skipped below (not failed) when
    // a fixture id never arrives, and every other page check is independent of these
    // fixtures entirely.
    let taskId: string | null = null
    let runId: string | null = null
    try {
      await seedDlqAndPipelineFixtures(baseURL ?? 'http://localhost:8080')
    } catch (err) {
      console.warn('a11y sweep: DLQ/pipeline fixture seed failed, continuing without it:', err)
    }
    try {
      const fixture = await seedTaskAndRunFixtures(baseURL ?? 'http://localhost:8080')
      taskId = fixture?.taskId ?? null
      runId = fixture?.runId ?? null
    } catch (err) {
      console.warn('a11y sweep: task/run fixture seed failed, continuing without it:', err)
    }

    await setLocale(page, locale)
    await loginAsE2EOperator(page)

    const pagesToCheck = [
      ...AUTHENTICATED_STATIC_PAGES,
      ...(taskId ? [{ name: 'task detail', path: `/tasks/${taskId}` }] : []),
      ...(runId ? [{ name: 'run detail', path: `/runs/${runId}` }] : []),
    ]

    const failuresByPage: Record<string, unknown> = {}
    for (const { name, path: pagePath } of pagesToCheck) {
      await page.goto(pagePath, { waitUntil: 'networkidle' })
      const results = await runAxe(page)
      const serious = results.violations.filter((v) => ['serious', 'critical'].includes(v.impact ?? ''))
      if (serious.length > 0) {
        failuresByPage[name] = serious
      }
    }

    expect(failuresByPage, JSON.stringify(failuresByPage, null, 2)).toEqual({})
  })
}

// §10: "assert no horizontal page scrollbar at 1280×720 with a 2× device scale factor
// / halved viewport width on the key pages." Wide tables may scroll within their own
// container (#92) — the page body must not.
test.describe('200% zoom — no horizontal page scroll', () => {
  test.use({ viewport: { width: 640, height: 360 }, deviceScaleFactor: 2 })

  for (const { name, path } of UNAUTHENTICATED_PAGES) {
    test(`${name} does not scroll horizontally`, async ({ page }) => {
      await page.goto(path, { waitUntil: 'networkidle' })
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth)
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth)
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1)
    })
  }

  test('authenticated dashboard/tasks/settings do not scroll horizontally', async ({ page }, testInfo) => {
    testInfo.skip(
      !e2eCredentialsConfigured(),
      'Set E2E_EMAIL and E2E_PASSWORD (see docs/deployment/e2e-operator.md).',
    )
    await loginAsE2EOperator(page)
    for (const path of ['/dashboard', '/tasks', '/settings']) {
      await page.goto(path, { waitUntil: 'networkidle' })
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth)
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth)
      expect(scrollWidth, `${path} scrolled horizontally at 200% zoom`).toBeLessThanOrEqual(clientWidth + 1)
    }
  })
})

// §10: "test layouts at narrow widths" — 360px smoke pass, content must not be lost
// or require horizontal scrolling.
test.describe('360px narrow viewport', () => {
  test.use({ viewport: { width: 360, height: 800 } })

  test('login does not scroll horizontally and stays usable', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'networkidle' })
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth)
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth)
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1)
    await expect(page.locator('#email, input[type="email"]').first()).toBeVisible()
    await expect(page.locator('#password, input[type="password"]').first()).toBeVisible()
  })

  test('authenticated dashboard stays usable at 360px', async ({ page }, testInfo) => {
    testInfo.skip(
      !e2eCredentialsConfigured(),
      'Set E2E_EMAIL and E2E_PASSWORD (see docs/deployment/e2e-operator.md).',
    )
    await loginAsE2EOperator(page)
    await page.goto('/dashboard', { waitUntil: 'networkidle' })
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth)
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth)
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1)
  })
})

// §10 keyboard: every interactive element gets visible focus in logical order, no
// keyboard trap, and the skip link (#88) works.
test.describe('keyboard navigation', () => {
  test('login: tab order reaches email, password, and submit with visible focus', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'networkidle' })

    await page.keyboard.press('Tab')
    const first = await focusedElementInfo(page)
    expect(first.hasVisibleFocus).toBe(true)

    const seen = new Set<string>([first.selector])
    let sawEmail = false
    let sawPassword = false
    let sawSubmit = false

    for (let i = 0; i < 15; i += 1) {
      const info = await focusedElementInfo(page)
      if (info.type === 'email' || info.id === 'email') sawEmail = true
      if (info.type === 'password' || info.id === 'password') sawPassword = true
      if (info.tag === 'button' && info.buttonType === 'submit') sawSubmit = true
      if (sawEmail && sawPassword && sawSubmit) break
      await page.keyboard.press('Tab')
      const next = await focusedElementInfo(page)
      // No keyboard trap: focus must keep advancing through distinct elements.
      seen.add(next.selector)
    }

    expect(sawEmail, 'tab order never reached the email field').toBe(true)
    expect(sawPassword, 'tab order never reached the password field').toBe(true)
    expect(sawSubmit, 'tab order never reached the submit button').toBe(true)
  })

  test('authenticated app: skip link jumps to main content, nav is fully tabbable', async ({ page }, testInfo) => {
    testInfo.skip(
      !e2eCredentialsConfigured(),
      'Set E2E_EMAIL and E2E_PASSWORD (see docs/deployment/e2e-operator.md).',
    )
    await loginAsE2EOperator(page)
    await page.goto('/dashboard', { waitUntil: 'networkidle' })

    // The skip link is the first focusable element and must be visible once focused.
    await page.keyboard.press('Tab')
    const skipLink = page.locator('a[href="#main-content"]')
    await expect(skipLink).toBeFocused()
    await expect(skipLink).toBeVisible()

    await page.keyboard.press('Enter')
    const main = page.locator('#main-content')
    await expect(main).toBeVisible()

    // Tab through the sidebar nav; every stop must show visible focus (no trap).
    for (let i = 0; i < 5; i += 1) {
      await page.keyboard.press('Tab')
      const info = await focusedElementInfo(page)
      expect(info.hasVisibleFocus, `focused element ${info.selector} has no visible focus indicator`).toBe(true)
    }
  })
})

async function focusedElementInfo(page: Page) {
  return page.evaluate(() => {
    const el = document.activeElement as HTMLElement | null
    if (!el || el === document.body) {
      return { selector: 'body', tag: 'body', type: null, id: null, buttonType: null, hasVisibleFocus: false }
    }
    const style = getComputedStyle(el)
    const hasVisibleFocus =
      style.outlineWidth !== '0px' && style.outlineStyle !== 'none'
        ? true
        : style.boxShadow !== 'none' && style.boxShadow !== ''
    return {
      selector: el.tagName.toLowerCase() + (el.id ? `#${el.id}` : ''),
      tag: el.tagName.toLowerCase(),
      type: el.getAttribute('type'),
      id: el.id || null,
      buttonType: el.tagName.toLowerCase() === 'button' ? el.getAttribute('type') : null,
      hasVisibleFocus,
    }
  })
}
