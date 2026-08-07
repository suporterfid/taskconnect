import { mkdirSync, readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { resolve } from 'node:path'

import { expect, test, type Page } from '@playwright/test'

import { e2eCredentialsConfigured, loginAsE2EOperator } from './helpers/auth'
import { seedTaskAndRunFixtures } from './helpers/seed'

const axeSource = readFileSync(createRequire(import.meta.url).resolve('axe-core/axe.min.js'), 'utf8')
const evidenceDirectory = resolve(process.cwd(), '../output/playwright/taskconnect')

mkdirSync(evidenceDirectory, { recursive: true })

type BrowserDiagnostics = {
  consoleErrors: string[]
  expectedAnonymousAuthErrors: string[]
  requestFailures: string[]
  expectedAbortedRequests: string[]
  badScriptResponses: string[]
}

function collectDiagnostics(page: Page): BrowserDiagnostics {
  const diagnostics: BrowserDiagnostics = {
    consoleErrors: [],
    expectedAnonymousAuthErrors: [],
    requestFailures: [],
    expectedAbortedRequests: [],
    badScriptResponses: [],
  }
  page.on('console', (message) => {
    if (message.type() === 'error') {
      const location = message.location()
      const entry = `${message.text()} @ ${location.url}:${location.lineNumber}`
      if (message.text().includes('401 (Unauthorized)') && location.url.endsWith('/api/v1/me')) {
        diagnostics.expectedAnonymousAuthErrors.push(entry)
      } else {
        diagnostics.consoleErrors.push(entry)
      }
    }
  })
  page.on('requestfailed', (request) => {
    const entry = `${request.method()} ${request.url()}: ${request.failure()?.errorText}`
    if (request.failure()?.errorText === 'net::ERR_ABORTED') {
      diagnostics.expectedAbortedRequests.push(entry)
    } else {
      diagnostics.requestFailures.push(entry)
    }
  })
  page.on('response', (response) => {
    if (response.request().resourceType() !== 'script') return
    const contentType = response.headers()['content-type'] ?? ''
    if (!/javascript|ecmascript|wasm/.test(contentType)) {
      diagnostics.badScriptResponses.push(`${response.status()} ${contentType} ${response.url()}`)
    }
  })
  return diagnostics
}

async function expectNoPageOverflow(page: Page): Promise<void> {
  const geometry = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
    bodyClientWidth: document.body.clientWidth,
    bodyScrollWidth: document.body.scrollWidth,
    offenders: Array.from(document.body.querySelectorAll<HTMLElement>('*'))
      .map((element) => {
        const rect = element.getBoundingClientRect()
        return {
          tag: element.tagName.toLowerCase(),
          id: element.id,
          className: element.className,
          left: rect.left,
          right: rect.right,
          width: rect.width,
          text: element.textContent?.trim().slice(0, 60),
        }
      })
      .filter((item) => item.left < -1 || item.right > window.innerWidth + 1)
      .slice(0, 20),
    internalOverflow: Array.from(document.body.querySelectorAll<HTMLElement>('*'))
      .filter((element) => element.scrollWidth > element.clientWidth + 1)
      .slice(0, 20)
      .map((element) => ({
        tag: element.tagName.toLowerCase(),
        id: element.id,
        className: element.className,
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
        overflowX: getComputedStyle(element).overflowX,
        text: element.textContent?.trim().slice(0, 60),
      })),
  }))
  expect(geometry.scrollWidth, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.clientWidth + 1)
}

async function expectNoClippedText(page: Page): Promise<void> {
  const clipped = await page.evaluate(() =>
    Array.from(document.body.querySelectorAll<HTMLElement>('*'))
      .filter((element) => {
        if (!element.textContent?.trim()) return false
        if (element.matches('input, textarea, select, option, pre, code, .table-scroll, .table-scroll *')) return false
        const style = getComputedStyle(element)
        const clipsInline = ['hidden', 'clip'].includes(style.overflowX)
        const clipsBlock = ['hidden', 'clip'].includes(style.overflowY)
        return (clipsInline && element.scrollWidth > element.clientWidth + 1)
          || (clipsBlock && element.scrollHeight > element.clientHeight + 1)
      })
      .slice(0, 20)
      .map((element) => ({
        selector: `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}`,
        text: element.textContent?.trim().slice(0, 80),
        client: [element.clientWidth, element.clientHeight],
        scroll: [element.scrollWidth, element.scrollHeight],
      })),
  )
  expect(clipped, JSON.stringify(clipped, null, 2)).toEqual([])
}

async function doubleVisibleTypography(page: Page) {
  return page.evaluate(() => {
    const visible = Array.from(document.body.querySelectorAll<HTMLElement>('*')).filter((element) => {
      const rect = element.getBoundingClientRect()
      return rect.width > 0 && rect.height > 0
    })
    const sample = document.querySelector<HTMLElement>('h1') ?? document.body
    const beforeStyle = getComputedStyle(sample)
    const before = {
      selector: sample.tagName.toLowerCase(),
      fontSize: Number.parseFloat(beforeStyle.fontSize),
      lineHeight: Number.parseFloat(beforeStyle.lineHeight),
    }

    for (const element of visible) {
      const style = getComputedStyle(element)
      const fontSize = Number.parseFloat(style.fontSize)
      const parsedLineHeight = Number.parseFloat(style.lineHeight)
      const lineHeight = Number.isFinite(parsedLineHeight) ? parsedLineHeight : fontSize * 1.2
      element.style.fontSize = `${fontSize * 2}px`
      element.style.lineHeight = `${lineHeight * 2}px`
    }

    const afterStyle = getComputedStyle(sample)
    return {
      adjustedElements: visible.length,
      before,
      after: {
        selector: sample.tagName.toLowerCase(),
        fontSize: Number.parseFloat(afterStyle.fontSize),
        lineHeight: Number.parseFloat(afterStyle.lineHeight),
      },
    }
  })
}

async function injectPseudoLocalization(page: Page, direction: 'ltr' | 'rtl'): Promise<void> {
  await page.evaluate((dir) => {
    document.documentElement.lang = dir === 'rtl' ? 'ar-XB' : 'en-XA'
    document.documentElement.dir = dir

    const shortTargets = document.querySelectorAll<HTMLElement>('label, button, a, option')
    for (const target of shortTargets) {
      if (target.children.length === 0 && target.textContent?.trim()) {
        const value = target.textContent.trim()
        target.textContent = dir === 'rtl' ? `Ù…Ø«Ø§Ù„ ${value} ${value}` : `[!! ${value} ${value} !!]`
      }
    }

    const generalTargets = document.querySelectorAll<HTMLElement>('h1, p')
    for (const target of generalTargets) {
      if (target.children.length === 0 && target.textContent?.trim()) {
        const value = target.textContent.trim()
        const expansion = value.slice(0, Math.max(1, Math.ceil(value.length * 0.3)))
        target.textContent = dir === 'rtl' ? `Ù†Øµ Ø§Ø®ØªØ¨Ø§Ø±ÙŠ ${value} ${expansion}` : `[${value} ${expansion}]`
      }
    }

    const surface = document.querySelector<HTMLElement>('form')?.parentElement ?? document.body
    const scripts = document.createElement('p')
    scripts.id = 'identity-script-fixture'
    scripts.dir = 'auto'
    scripts.style.overflowWrap = 'anywhere'
    scripts.innerHTML = dir === 'rtl'
      ? 'Ù…Ø¹Ø±Ù <bdi>task-123@example.test</bdi> ØªÙØ§ØµÙŠÙ„ Ø¥Ø¶Ø§ÙÙŠØ© ×•×ž×™×“×¢ × ×•×¡×£'
      : 'æ—¥æœ¬èªžã®é•·ã„ãƒ†ã‚­ã‚¹ãƒˆã¨ä¸­æ–‡æ··åˆå†…å®¹ã‚’è‡ªç„¶ã«æŠ˜ã‚Šè¿”ã™ãƒ†ã‚¹ãƒˆภาษาไทยà¤¦à¥‡à¤µà¤¨à¤¾à¤—à¤°à¥€'
    surface.append(scripts)
  }, direction)
}

async function expectTerminalControlReachable(page: Page): Promise<Record<string, number>> {
  const terminal = page.locator('a, button, input, select, textarea').last()
  await terminal.focus()
  await terminal.evaluate((element) => element.scrollIntoView({ block: 'end', inline: 'nearest' }))
  const box = await terminal.boundingBox()
  expect(box).not.toBeNull()
  const viewport = page.viewportSize()
  expect(viewport).not.toBeNull()
  expect(box!.y).toBeGreaterThanOrEqual(-1)
  expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height + 1)
  return { x: box!.x, y: box!.y, width: box!.width, height: box!.height }
}

async function runAxe(page: Page) {
  await page.addScriptTag({ content: axeSource })
  return page.evaluate(async () => {
    // @ts-expect-error axe is injected for real-browser verification.
    return window.axe.run(document, {
      runOnly: { type: 'rule', values: ['color-contrast'] },
      resultTypes: ['violations'],
    })
  }) as Promise<{ violations: Array<{ id: string; impact: string | null; nodes: unknown[] }> }>
}

test('light, dark, and system honor persistence, live OS changes, reload, and no-flash shell state', async ({ page }) => {
  const diagnostics = collectDiagnostics(page)
  await page.emulateMedia({ colorScheme: 'dark' })
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  expect(await page.locator('html').evaluate((element) => getComputedStyle(element).colorScheme)).toBe('dark')
  expect(await page.locator('meta[name="theme-color"]').getAttribute('content')).toBe('#191919')
  expect(await page.evaluate(() => localStorage.getItem('taskconnect.theme'))).toBeNull()

  await page.emulateMedia({ colorScheme: 'light' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')

  await page.evaluate(() => localStorage.setItem('taskconnect.theme', 'dark'))
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await page.emulateMedia({ colorScheme: 'light' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await page.screenshot({ path: resolve(evidenceDirectory, 'login-dark.png'), fullPage: true })

  await page.evaluate(() => localStorage.setItem('taskconnect.theme', 'system'))
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
  await page.emulateMedia({ colorScheme: 'dark' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await page.screenshot({ path: resolve(evidenceDirectory, 'login-system-dark.png'), fullPage: true })

  expect(diagnostics.consoleErrors).toEqual([])
  expect(diagnostics.expectedAnonymousAuthErrors.length).toBeGreaterThan(0)
  expect(diagnostics.requestFailures).toEqual([])
  expect(diagnostics.badScriptResponses).toEqual([])
})

for (const theme of ['light', 'dark'] as const) {
  test(`${theme} auth and long-form surfaces pass axe color contrast`, async ({ page }) => {
    await page.addInitScript((preference) => localStorage.setItem('taskconnect.theme', preference), theme)
    for (const path of ['/login', '/reset-password?token=e2e-token&email=e2e%40example.test']) {
      await page.goto(path, { waitUntil: 'networkidle' })
      const results = await runAxe(page)
      expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([])
    }
  })
}

for (const surface of [
  { name: 'login-short', path: '/login' },
  { name: 'reset-long', path: '/reset-password?token=e2e-token&email=e2e%40example.test' },
] as const) {
  test(`${surface.name} reflows at 320px with real doubled computed typography`, async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 })
    await page.goto(surface.path, { waitUntil: 'networkidle' })
    await injectPseudoLocalization(page, 'ltr')
    const typography = await doubleVisibleTypography(page)

    expect(typography.adjustedElements).toBeGreaterThan(5)
    expect(typography.after.fontSize).toBeCloseTo(typography.before.fontSize * 2, 3)
    expect(typography.after.lineHeight).toBeCloseTo(typography.before.lineHeight * 2, 3)
    await expectNoPageOverflow(page)
    await expectNoClippedText(page)
    const terminal = await expectTerminalControlReachable(page)

    console.log(`${surface.name} typography`, JSON.stringify({ typography, terminal }))
    await page.screenshot({
      path: resolve(evidenceDirectory, `${surface.name}-320-double-type.png`),
      fullPage: true,
    })
  })
}

test('en-XA expansion, CJK wrapping, and ar-XB mixed direction retain complete values', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 800 })
  await page.goto('/reset-password?token=e2e-token&email=e2e%40example.test', { waitUntil: 'networkidle' })
  await injectPseudoLocalization(page, 'ltr')
  await expectNoPageOverflow(page)
  await expectNoClippedText(page)
  await expect(page.locator('#identity-script-fixture')).toContainText('æ—¥æœ¬èªž')
  await page.screenshot({ path: resolve(evidenceDirectory, 'pseudo-en-XA-cjk.png'), fullPage: true })

  await page.reload({ waitUntil: 'networkidle' })
  await injectPseudoLocalization(page, 'rtl')
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.locator('#identity-script-fixture bdi')).toHaveText('task-123@example.test')
  await expectNoPageOverflow(page)
  await expectNoClippedText(page)
  await page.screenshot({ path: resolve(evidenceDirectory, 'pseudo-ar-XB-mixed.png'), fullPage: true })
})

test('reduced motion and forced colors preserve visible control and focus contracts', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce', forcedColors: 'active' })
  await page.goto('/login', { waitUntil: 'networkidle' })
  const button = page.getByRole('button', { name: /sign in|entrar/i })
  await button.focus()
  const behavior = await button.evaluate((element) => {
    const style = getComputedStyle(element)
    return {
      transitionDuration: style.transitionDuration,
      animationDuration: style.animationDuration,
      forcedColorAdjust: style.forcedColorAdjust,
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth,
      borderStyle: style.borderStyle,
    }
  })
  expect(behavior.transitionDuration).toMatch(/0\.001s|1ms/)
  expect(behavior.animationDuration).toMatch(/0\.001s|1ms/)
  expect(behavior.forcedColorAdjust).toBe('auto')
  expect(behavior.outlineStyle).not.toBe('none')
  expect(behavior.outlineWidth).not.toBe('0px')
  expect(behavior.borderStyle).not.toBe('none')
  await page.screenshot({ path: resolve(evidenceDirectory, 'forced-colors-focus.png'), fullPage: true })
})

test('authenticated RTL shell restores drawer access and exposes keyboard table controls', async ({ page }, testInfo) => {
  testInfo.skip(!e2eCredentialsConfigured(), 'Set E2E_EMAIL and E2E_PASSWORD for authenticated identity proof.')
  test.setTimeout(60_000)

  await seedTaskAndRunFixtures(String(testInfo.project.use.baseURL ?? 'http://localhost:8080'))
  await loginAsE2EOperator(page)
  const diagnostics = collectDiagnostics(page)
  await page.setViewportSize({ width: 320, height: 800 })
  await page.goto('/tasks', { waitUntil: 'networkidle' })
  await page.evaluate(() => {
    document.documentElement.lang = 'ar-XB'
    document.documentElement.dir = 'rtl'
    for (const item of document.querySelectorAll<HTMLElement>('.nav-item')) {
      const value = item.textContent?.trim() ?? ''
      item.textContent = `Ù…Ø«Ø§Ù„ ${value} ${value}`
    }
  })

  const sidebar = page.locator('#app-sidebar')
  await expect(sidebar).toHaveAttribute('inert', '')
  const open = page.locator('button[aria-controls="app-sidebar"]')
  await open.focus()
  await expect(open).toBeFocused()
  await open.press('Enter')
  await expect(sidebar).not.toHaveAttribute('inert', '')
  await expect(sidebar).toHaveCSS('transform', 'matrix(1, 0, 0, 1, 0, 0)')
  await expect(sidebar).toHaveCSS('right', '0px')
  await expectNoPageOverflow(page)

  const sidebarNav = sidebar.getByRole('navigation')
  await sidebarNav.focus()
  await sidebarNav.press('Escape')
  await expect(sidebar).toHaveAttribute('inert', '')
  const region = page.getByRole('region', { name: /table|tabela/i }).first()
  await region.focus()
  await expect(region).toBeFocused()
  await expectNoPageOverflow(page)
  await page.screenshot({ path: resolve(evidenceDirectory, 'app-tasks-rtl-320.png'), fullPage: true })

  await page.setViewportSize({ width: 1280, height: 800 })
  await expect(sidebar).not.toHaveAttribute('inert', '')
  await page.goto('/settings', { waitUntil: 'networkidle' })
  await expect(page.locator('[data-theme-select] select')).toBeVisible()
  await page.locator('[data-theme-select] select').selectOption('light')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')

  expect(diagnostics.consoleErrors).toEqual([])
  expect(diagnostics.requestFailures).toEqual([])
  expect(diagnostics.badScriptResponses).toEqual([])
})
