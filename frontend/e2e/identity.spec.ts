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

async function expectBoundedDocument(page: Page, maxScrollHeight = 10_000) {
  const geometry = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
    clientHeight: document.documentElement.clientHeight,
    scrollHeight: document.documentElement.scrollHeight,
    bodyClientWidth: document.body.clientWidth,
    bodyScrollWidth: document.body.scrollWidth,
    bodyClientHeight: document.body.clientHeight,
    bodyScrollHeight: document.body.scrollHeight,
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
  expect(geometry.bodyScrollWidth, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.bodyClientWidth + 1)
  expect(geometry.scrollHeight, JSON.stringify(geometry)).toBeLessThanOrEqual(maxScrollHeight)
  return geometry
}

async function expectNoClippedText(page: Page): Promise<void> {
  const clipped = await page.evaluate(() =>
    Array.from(document.body.querySelectorAll<HTMLElement>('*'))
      .filter((element) => {
        if (element.matches('.sr-only') || element.closest('[inert]')) return false
        if (element.matches('input, textarea, select')) {
          const rect = element.getBoundingClientRect()
          return rect.width <= 0 || rect.height <= 0 || element.clientWidth <= 0 || element.clientHeight <= 0
        }
        if (!element.textContent?.trim()) return false
        if (element.matches('option, pre, code, .table-scroll, .table-scroll *')) return false
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
    const visible = Array.from(document.body.querySelectorAll<HTMLElement>(
      'h1, h2, h3, p, label, button, a, input, textarea, select, [role="button"], [role="status"]',
    )).filter((element) => {
      const rect = element.getBoundingClientRect()
      const style = getComputedStyle(element)
      return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden'
    })
    const sample = document.querySelector<HTMLElement>('h1') ?? document.body
    const documentBefore = {
      clientWidth: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      clientHeight: document.documentElement.clientHeight,
      scrollHeight: document.documentElement.scrollHeight,
    }
    const snapshots = visible.map((element) => {
      const style = getComputedStyle(element)
      const fontSize = Number.parseFloat(style.fontSize)
      const parsedLineHeight = Number.parseFloat(style.lineHeight)
      const lineHeight = Number.isFinite(parsedLineHeight) ? parsedLineHeight : fontSize * 1.2
      return { element, fontSize, lineHeight }
    })
    const sampleSnapshot = snapshots.find(({ element }) => element === sample)
      ?? { element: sample, fontSize: Number.parseFloat(getComputedStyle(sample).fontSize), lineHeight: Number.parseFloat(getComputedStyle(sample).lineHeight) }

    // Snapshot every computed value first, then apply one exact multiplier. This avoids
    // recursively multiplying inherited values when both an ancestor and child are targets.
    for (const snapshot of snapshots) {
      snapshot.element.style.fontSize = `${snapshot.fontSize * 2}px`
      snapshot.element.style.lineHeight = `${snapshot.lineHeight * 2}px`
    }

    const ratios = snapshots.map(({ element, fontSize, lineHeight }) => {
      const style = getComputedStyle(element)
      return {
        fontSize: Number.parseFloat(style.fontSize) / fontSize,
        lineHeight: Number.parseFloat(style.lineHeight) / lineHeight,
      }
    })
    const sampleAfter = getComputedStyle(sample)
    const fontRatios = ratios.map((ratio) => ratio.fontSize).filter(Number.isFinite)
    const lineRatios = ratios.map((ratio) => ratio.lineHeight).filter(Number.isFinite)
    const documentAfter = {
      clientWidth: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      clientHeight: document.documentElement.clientHeight,
      scrollHeight: document.documentElement.scrollHeight,
    }
    return {
      adjustedElements: snapshots.length,
      before: {
        selector: sample.tagName.toLowerCase(),
        fontSize: sampleSnapshot.fontSize,
        lineHeight: sampleSnapshot.lineHeight,
      },
      after: {
        selector: sample.tagName.toLowerCase(),
        fontSize: Number.parseFloat(sampleAfter.fontSize),
        lineHeight: Number.parseFloat(sampleAfter.lineHeight),
      },
      minFontRatio: Math.min(...fontRatios),
      maxFontRatio: Math.max(...fontRatios),
      minLineRatio: Math.min(...lineRatios),
      maxLineRatio: Math.max(...lineRatios),
      documentBefore,
      documentAfter,
    }
  })
}

async function injectPseudoLocalization(page: Page, direction: 'ltr' | 'rtl'): Promise<void> {
  await page.evaluate((dir) => {
    document.documentElement.lang = dir === 'rtl' ? 'ar-XB' : 'en-XA'
    document.documentElement.dir = dir

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT)
    const textNodes: Text[] = []
    while (walker.nextNode()) textNodes.push(walker.currentNode as Text)
    for (const node of textNodes) {
      const parent = node.parentElement
      const value = node.data.trim()
      if (!parent || !value || parent.closest('script, style, code, pre, bdi, #identity-script-fixture')) continue
      const short = Boolean(parent.closest('button, a, label, legend, option, th, [role="menuitem"], [role="tab"]'))
      const expansion = short ? value : value.slice(0, Math.max(1, Math.ceil(value.length * 0.3)))
      const expanded = short
        ? (dir === 'rtl' ? `\u0645\u062b\u0627\u0644 ${value} ${value}` : `[!! ${value} ${value} !!]`)
        : (dir === 'rtl' ? `\u0646\u0635 \u0627\u062e\u062a\u0628\u0627\u0631\u064a ${value} ${expansion}` : `[${value} ${expansion}]`)
      node.data = node.data.replace(value, expanded)
      parent.dataset.pseudoLocalized = short ? 'short-2x' : 'general-30'
    }

    const surface = document.querySelector<HTMLElement>('form')?.parentElement ?? document.body
    const fixture = document.createElement('div')
    fixture.id = 'identity-script-fixture'
    fixture.dir = 'auto'
    fixture.style.maxInlineSize = '14rem'
    fixture.style.whiteSpace = 'normal'
    fixture.style.overflowWrap = 'normal'

    const values = [
      ['arabic', 'ar', 'rtl', '\u0645\u0631\u062d\u0628\u0627 \u0628\u0627\u0644\u0639\u0627\u0644\u0645'],
      ['hebrew', 'he', 'rtl', '\u05e9\u05dc\u05d5\u05dd \u05e2\u05d5\u05dc\u05dd'],
      ['cjk', 'ja', 'ltr', '\u65e5\u672c\u8a9e\u306e\u9577\u3044\u30c6\u30ad\u30b9\u30c8\u3068\u4e2d\u6587\u6df7\u5408\u5185\u5bb9\u3092\u81ea\u7136\u306b\u6298\u308a\u8fd4\u3059\u30c6\u30b9\u30c8'],
      ['thai', 'th', 'ltr', '\u0e20\u0e32\u0e29\u0e32\u0e44\u0e17\u0e22\u0e17\u0e14\u0e2a\u0e2d\u0e1a\u0e01\u0e32\u0e23\u0e15\u0e31\u0e14\u0e04\u0e33'],
      ['devanagari', 'hi', 'ltr', '\u0926\u0947\u0935\u0928\u093e\u0917\u0930\u0940 \u092a\u093e\u0920 \u092a\u0930\u0940\u0915\u094d\u0937\u0923'],
    ] as const
    for (const [name, lang, scriptDirection, value] of values) {
      const script = document.createElement('span')
      script.dataset.script = name
      script.lang = lang
      script.dir = scriptDirection
      script.textContent = value
      script.style.display = 'block'
      fixture.append(script)
    }
    const mixed = document.createElement('p')
    mixed.dataset.script = 'mixed'
    mixed.dir = 'rtl'
    mixed.append(`${values[0][3]} `)
    const identifier = document.createElement('bdi')
    identifier.dir = 'ltr'
    identifier.textContent = 'task-123@example.test'
    mixed.append(identifier, ` ${values[1][3]}`)
    fixture.append(mixed)
    surface.append(fixture)
  }, direction)
}

async function auditInteractiveControls(page: Page, checkPairwiseOverlap = true) {
  const selector = 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
  const controls = page.locator(selector)
  const geometry = await page.evaluate(({ selector, checkPairwiseOverlap }) => {
    const allElements = Array.from(document.querySelectorAll<HTMLElement>(selector))
    const indexedElements = allElements
      .map((element, sourceIndex) => ({ element, sourceIndex }))
      .filter(({ element }) => {
        const rect = element.getBoundingClientRect()
        const style = getComputedStyle(element)
        return !element.closest('[inert]')
          && rect.width > 0
          && rect.height > 0
          && rect.right > 0
          && rect.left < window.innerWidth
          && style.visibility !== 'hidden'
          && style.display !== 'none'
      })
    const elements = indexedElements.map(({ element }) => element)
    const boxes = indexedElements.map(({ element, sourceIndex }, index) => {
      const rect = element.getBoundingClientRect()
      const documentedInlineOrDataException = Boolean(
        element.closest('.table-scroll') || element.matches('[data-inline-action]'),
      )
      return {
        index,
        sourceIndex,
        tag: element.tagName.toLowerCase(),
        x: rect.x,
        y: rect.y,
        width: rect.width,
        height: rect.height,
        documentedInlineOrDataException,
      }
    })
    const overlaps: Array<[number, number]> = []
    if (checkPairwiseOverlap) {
      for (let first = 0; first < elements.length; first += 1) {
        for (let second = first + 1; second < elements.length; second += 1) {
          if (elements[first].contains(elements[second]) || elements[second].contains(elements[first])) continue
          const a = elements[first].getBoundingClientRect()
          const b = elements[second].getBoundingClientRect()
          const overlapWidth = Math.min(a.right, b.right) - Math.max(a.left, b.left)
          const overlapHeight = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top)
          if (overlapWidth > 1 && overlapHeight > 1) overlaps.push([first, second])
        }
      }
    }
    return { visibleCount: elements.length, boxes, overlaps }
  }, { selector, checkPairwiseOverlap })
  expect(geometry.visibleCount).toBeGreaterThan(0)
  expect(geometry.boxes.every((box) => box.width > 0 && box.height > 0), JSON.stringify(geometry)).toBe(true)
  expect(
    geometry.boxes.every((box) => box.documentedInlineOrDataException || (box.width >= 44 && box.height >= 44)),
    JSON.stringify(geometry),
  ).toBe(true)
  expect(geometry.overlaps, JSON.stringify(geometry)).toEqual([])

  let focused = 0
  for (const box of geometry.boxes) {
    const control = controls.nth(box.sourceIndex)
    if (await control.isDisabled()) continue
    await control.evaluate((element) => element.scrollIntoView({ block: 'center', inline: 'center' }))
    await control.focus()
    const reachability = await control.evaluate((element) => {
      const rect = element.getBoundingClientRect()
      const viewport = window.visualViewport
      const left = viewport?.offsetLeft ?? 0
      const top = viewport?.offsetTop ?? 0
      const right = left + (viewport?.width ?? window.innerWidth)
      const bottom = top + (viewport?.height ?? window.innerHeight)
      const centerX = Math.max(left, Math.min(right - 1, rect.left + rect.width / 2))
      const centerY = Math.max(top, Math.min(bottom - 1, rect.top + rect.height / 2))
      const hit = document.elementFromPoint(centerX, centerY)
      return {
        active: document.activeElement === element,
        inViewport: rect.right > left && rect.left < right && rect.bottom > top && rect.top < bottom,
        unobscured: hit === element || element.contains(hit) || Boolean(hit?.contains(element)),
      }
    })
    expect(reachability.active, JSON.stringify({ box, reachability })).toBe(true)
    expect(reachability.inViewport, JSON.stringify({ box, reachability })).toBe(true)
    expect(reachability.unobscured, JSON.stringify({ box, reachability })).toBe(true)
    focused += 1
  }
  expect(focused).toBe(geometry.visibleCount)
  return { ...geometry, focused }
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
    expect(typography.minFontRatio).toBeCloseTo(2, 5)
    expect(typography.maxFontRatio).toBeCloseTo(2, 5)
    expect(typography.minLineRatio).toBeCloseTo(2, 5)
    expect(typography.maxLineRatio).toBeCloseTo(2, 5)
    expect(typography.documentBefore.clientWidth).toBe(320)
    expect(typography.documentAfter.clientWidth).toBe(320)
    const documentGeometry = await expectBoundedDocument(page, 6_000)
    expect(typography.documentAfter.scrollWidth).toBeLessThanOrEqual(321)
    await expectNoClippedText(page)
    const controls = await auditInteractiveControls(page)

    console.log(`${surface.name} typography`, JSON.stringify({ typography, documentGeometry, controls }))
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
  await expectBoundedDocument(page)
  await expectNoClippedText(page)
  const scripts = await page.locator('#identity-script-fixture').evaluate((fixture) => {
    const value = (name: string) => fixture.querySelector<HTMLElement>(`[data-script="${name}"]`)?.textContent ?? ''
    const arabic = value('arabic')
    const canvas = document.createElement('canvas').getContext('2d')!
    canvas.font = getComputedStyle(fixture.querySelector<HTMLElement>('[data-script="arabic"]')!).font
    const joinedWidth = canvas.measureText(arabic.replace(/\s/g, '')).width
    const isolatedWidth = [...arabic.replace(/\s/g, '')]
      .reduce((sum, character) => sum + canvas.measureText(character).width, 0)
    const rendered = Array.from(fixture.querySelectorAll<HTMLElement>('[data-script]')).map((element) => ({
      script: element.dataset.script,
      width: element.getBoundingClientRect().width,
      height: element.getBoundingClientRect().height,
      clientWidth: element.clientWidth,
      scrollWidth: element.scrollWidth,
      lineHeight: Number.parseFloat(getComputedStyle(element).lineHeight),
      direction: getComputedStyle(element).direction,
      letterSpacing: getComputedStyle(element).letterSpacing,
    }))
    return {
      arabic: /\p{Script=Arabic}/u.test(arabic),
      hebrew: /\p{Script=Hebrew}/u.test(value('hebrew')),
      han: /\p{Script=Han}/u.test(value('cjk')),
      hiragana: /\p{Script=Hiragana}/u.test(value('cjk')),
      thai: /\p{Script=Thai}/u.test(value('thai')),
      devanagari: /\p{Script=Devanagari}/u.test(value('devanagari')),
      hasReplacement: fixture.textContent?.includes('\uFFFD') ?? false,
      arabicShapingDelta: Math.abs(joinedWidth - isolatedWidth),
      rendered,
    }
  })
  expect(scripts).toMatchObject({
    arabic: true,
    hebrew: true,
    han: true,
    hiragana: true,
    thai: true,
    devanagari: true,
    hasReplacement: false,
  })
  expect(scripts.arabicShapingDelta).toBeGreaterThan(0.1)
  expect(scripts.rendered.every((script) => script.width > 0 && script.height > 0)).toBe(true)
  expect(scripts.rendered.find((script) => script.script === 'arabic')).toMatchObject({ direction: 'rtl' })
  expect(scripts.rendered.find((script) => script.script === 'hebrew')).toMatchObject({ direction: 'rtl' })
  const cjk = scripts.rendered.find((script) => script.script === 'cjk')!
  expect(cjk.scrollWidth).toBeLessThanOrEqual(cjk.clientWidth + 1)
  expect(cjk.height).toBeGreaterThan(cjk.lineHeight * 1.5)
  await page.screenshot({ path: resolve(evidenceDirectory, 'pseudo-en-XA-cjk.png'), fullPage: true })

  await page.reload({ waitUntil: 'networkidle' })
  await injectPseudoLocalization(page, 'rtl')
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.locator('#identity-script-fixture bdi')).toHaveText('task-123@example.test')
  await expect(page.locator('#identity-script-fixture bdi')).toHaveAttribute('dir', 'ltr')
  const mixedDirection = await page.locator('[data-script="mixed"]').evaluate((element) => ({
    direction: getComputedStyle(element).direction,
    hasArabic: /\p{Script=Arabic}/u.test(element.textContent ?? ''),
    hasHebrew: /\p{Script=Hebrew}/u.test(element.textContent ?? ''),
    hasLatinIdentifier: element.querySelector('bdi')?.textContent === 'task-123@example.test',
  }))
  expect(mixedDirection).toEqual({ direction: 'rtl', hasArabic: true, hasHebrew: true, hasLatinIdentifier: true })
  await expectBoundedDocument(page)
  await expectNoClippedText(page)
  await page.screenshot({ path: resolve(evidenceDirectory, 'pseudo-ar-XB-mixed.png'), fullPage: true })
})

test('Chromium 200% page scale preserves form operability in the visual viewport', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'CDP page-scale evidence is Chromium-specific.')
  await page.setViewportSize({ width: 640, height: 800 })
  await page.goto('/reset-password?token=e2e-token&email=e2e%40example.test', { waitUntil: 'networkidle' })
  const cdp = await page.context().newCDPSession(page)
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 2 })
  await expect.poll(() => page.evaluate(() => window.visualViewport?.scale)).toBe(2)
  const viewport = await page.evaluate(() => ({
    scale: window.visualViewport?.scale,
    width: window.visualViewport?.width,
    height: window.visualViewport?.height,
    layoutWidth: document.documentElement.clientWidth,
    layoutHeight: document.documentElement.clientHeight,
  }))
  expect(viewport).toMatchObject({ scale: 2, layoutWidth: 640, layoutHeight: 800 })
  expect(viewport.width).toBeCloseTo(320, 0)
  expect(viewport.height).toBeCloseTo(400, 0)
  const documentGeometry = await expectBoundedDocument(page)
  await expectNoClippedText(page)
  const controls = await auditInteractiveControls(page)
  const password = page.locator('input[type="password"]').first()
  await password.fill('Page-scale-operable-123!')
  await expect(password).toHaveValue('Page-scale-operable-123!')
  console.log('page-scale-200', JSON.stringify({ viewport, documentGeometry, controls }))
  await page.screenshot({ path: resolve(evidenceDirectory, 'reset-page-scale-200.png') })
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 1 })
})

test('Chromium 400% page scale from 1280 yields a complete 320px visual viewport', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'CDP page-scale evidence is Chromium-specific.')
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/reset-password?token=e2e-token&email=e2e%40example.test', { waitUntil: 'networkidle' })
  await injectPseudoLocalization(page, 'ltr')
  const cdp = await page.context().newCDPSession(page)
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 4 })
  await expect.poll(() => page.evaluate(() => window.visualViewport?.scale)).toBe(4)
  const viewport = await page.evaluate(() => ({
    scale: window.visualViewport?.scale,
    width: window.visualViewport?.width,
    height: window.visualViewport?.height,
    layoutWidth: document.documentElement.clientWidth,
  }))
  expect(viewport).toMatchObject({ scale: 4, layoutWidth: 1280 })
  expect(viewport.width).toBeCloseTo(320, 0)
  const documentGeometry = await expectBoundedDocument(page)
  await expectNoClippedText(page)
  const controls = await auditInteractiveControls(page, false)
  const password = page.locator('input[type="password"]').first()
  await password.fill('Page-scale-400-operable-123!')
  await expect(password).toHaveValue('Page-scale-400-operable-123!')
  console.log('page-scale-400', JSON.stringify({ viewport, documentGeometry, controls }))
  await page.screenshot({ path: resolve(evidenceDirectory, 'reset-page-scale-400.png') })
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 1 })
})

for (const width of [480, 768, 1024, 1280] as const) {
  test(`anonymous surface supports exact 200% text resize at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/reset-password?token=e2e-token&email=e2e%40example.test', { waitUntil: 'networkidle' })
    await injectPseudoLocalization(page, 'ltr')
    const typography = await doubleVisibleTypography(page)
    expect(typography.minFontRatio).toBeCloseTo(2, 5)
    expect(typography.maxFontRatio).toBeCloseTo(2, 5)
    await expectBoundedDocument(page)
    await expectNoClippedText(page)
    await auditInteractiveControls(page, false)
    console.log(`text-resize-anonymous-${width}`, JSON.stringify(typography))
  })
}

test('real form controls preserve composition and pasted scripts in Chromium', async ({ page }) => {
  await page.goto('/reset-password?token=e2e-token&email=e2e%40example.test', { waitUntil: 'networkidle' })
  const token = page.locator('#token')
  await token.evaluate((element) => {
    const input = element as HTMLInputElement
    input.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true, data: '\u65e5' }))
    input.value = '\u65e5\u672c'
    input.dispatchEvent(new InputEvent('input', { bubbles: true, data: '\u672c', isComposing: true }))
  })
  await expect(token).toHaveValue('\u65e5\u672c')
  await token.evaluate((element) => {
    element.dispatchEvent(new CompositionEvent('compositionend', { bubbles: true, data: '\u65e5\u672c' }))
  })
  await expect(token).toHaveValue('\u65e5\u672c')

  const email = page.locator('#email')
  for (const value of ['\u0645\u0631\u062d\u0628\u0627@example.test', '\u0e2a\u0e27\u0e31\u0e2a\u0e14\u0e35@example.test', '\u0928\u092e\u0938\u094d\u0924\u0947@example.test']) {
    await email.fill(value)
    await expect(email).toHaveValue(value)
  }
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
      item.dataset.expectedExpandedLabel = `\u0645\u062b\u0627\u0644 ${value} ${value}`
      item.textContent = item.dataset.expectedExpandedLabel
    }
  })

  const rootTransforms = await page.evaluate(() => ({
    html: getComputedStyle(document.documentElement).transform,
    app: getComputedStyle(document.querySelector<HTMLElement>('#app')!).transform,
    shell: getComputedStyle(document.querySelector<HTMLElement>('.app-shell')!).transform,
  }))
  expect(rootTransforms).toEqual({ html: 'none', app: 'none', shell: 'none' })

  const sidebar = page.locator('#app-sidebar')
  await expect(sidebar).toHaveAttribute('inert', '')
  const topbarToggle = page.locator('button[aria-controls="app-topbar-secondary"]')
  await expect(topbarToggle).toBeVisible()
  await expect(page.locator('#app-topbar-secondary')).toBeHidden()
  await topbarToggle.click()
  await expect(page.locator('#app-topbar-secondary')).toBeVisible()
  await topbarToggle.click()
  await expect(page.locator('#app-topbar-secondary')).toBeHidden()
  const open = page.locator('button[aria-controls="app-sidebar"]')
  await open.focus()
  await expect(open).toBeFocused()
  await open.press('Enter')
  await expect(sidebar).not.toHaveAttribute('inert', '')
  await expect(sidebar).toHaveCSS('transform', 'matrix(1, 0, 0, 1, 0, 0)')
  await expect(sidebar).toHaveCSS('right', '0px')
  const layers = await page.evaluate(() => {
    const root = getComputedStyle(document.documentElement)
    const value = (name: string) => Number.parseInt(root.getPropertyValue(name), 10)
    return {
      declared: {
        header: value('--layer-header'), sidebar: value('--layer-sidebar'),
        popover: value('--layer-popover'), toast: value('--layer-toast'),
        modal: value('--layer-modal'), blocking: value('--layer-blocking'),
      },
      rendered: {
        header: Number.parseInt(getComputedStyle(document.querySelector<HTMLElement>('.app-header')!).zIndex, 10),
        sidebar: Number.parseInt(getComputedStyle(document.querySelector<HTMLElement>('.app-sidebar')!).zIndex, 10),
        scrim: Number.parseInt(getComputedStyle(document.querySelector<HTMLElement>('.app-scrim')!).zIndex, 10),
        skip: Number.parseInt(getComputedStyle(document.querySelector<HTMLElement>('.skip-link')!).zIndex, 10),
      },
    }
  })
  expect(layers).toEqual({
    declared: { header: 10, sidebar: 20, popover: 30, toast: 40, modal: 50, blocking: 60 },
    rendered: { header: 10, sidebar: 20, scrim: 20, skip: 60 },
  })
  const selectedColors = await page.locator('[aria-current="page"]').evaluate((element) => {
    const resolve = (token: string) => {
      const probe = document.createElement('span')
      probe.style.backgroundColor = `var(${token})`
      document.body.append(probe)
      const color = getComputedStyle(probe).backgroundColor
      probe.remove()
      return color
    }
    return {
      actual: getComputedStyle(element).backgroundColor,
      selected: resolve('--color-bg-selected'),
      hover: resolve('--color-bg-hover'),
    }
  })
  expect(selectedColors.actual).toBe(selectedColors.selected)
  expect(selectedColors.selected).not.toBe(selectedColors.hover)
  const sidebarBox = await sidebar.boundingBox()
  expect(sidebarBox).not.toBeNull()
  expect(sidebarBox!.x).toBeGreaterThanOrEqual(-1)
  expect(sidebarBox!.x + sidebarBox!.width).toBeLessThanOrEqual(321)
  const expandedLabels = await sidebar.locator('.nav-item').evaluateAll((items) => items.map((item) => ({
    complete: item.textContent === (item as HTMLElement).dataset.expectedExpandedLabel,
    visible: (item as HTMLElement).scrollWidth <= (item as HTMLElement).clientWidth + 1
      && (item as HTMLElement).scrollHeight <= (item as HTMLElement).clientHeight + 1,
    hasArabic: /\p{Script=Arabic}/u.test(item.textContent ?? ''),
  })))
  expect(expandedLabels.length).toBeGreaterThan(2)
  expect(expandedLabels.every((item) => item.complete && item.visible && item.hasArabic), JSON.stringify(expandedLabels)).toBe(true)
  await expectBoundedDocument(page)
  await page.screenshot({ path: resolve(evidenceDirectory, 'app-tasks-rtl-320.png'), fullPage: true })

  const sidebarNav = sidebar.getByRole('navigation')
  await sidebarNav.focus()
  await sidebarNav.press('Escape')
  await expect(sidebar).toHaveAttribute('inert', '')
  const region = page.getByRole('region', { name: /table|tabela/i }).first()
  await region.focus()
  await expect(region).toBeFocused()
  await expectBoundedDocument(page)

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

test('authenticated representative app passes axe contrast in light and dark themes', async ({ page }, testInfo) => {
  testInfo.skip(!e2eCredentialsConfigured(), 'Set E2E_EMAIL and E2E_PASSWORD for authenticated axe proof.')
  test.setTimeout(60_000)

  await seedTaskAndRunFixtures(String(testInfo.project.use.baseURL ?? 'http://localhost:8080'))
  await loginAsE2EOperator(page)
  const diagnostics = collectDiagnostics(page)
  for (const theme of ['light', 'dark'] as const) {
    await page.evaluate((preference) => localStorage.setItem('taskconnect.theme', preference), theme)
    await page.goto('/tasks', { waitUntil: 'networkidle' })
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
    const results = await runAxe(page)
    console.log(`authenticated-${theme}-axe`, JSON.stringify(results.violations))
    expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([])
    await page.screenshot({
      path: resolve(evidenceDirectory, `app-tasks-${theme}.png`),
      fullPage: true,
    })
  }
  expect(diagnostics.consoleErrors).toEqual([])
  expect(diagnostics.requestFailures).toEqual([])
  expect(diagnostics.badScriptResponses).toEqual([])
})

test('authenticated surface covers text-resize widths, shipped bidi, and directional controls', async ({ page }, testInfo) => {
  testInfo.skip(!e2eCredentialsConfigured(), 'Set E2E_EMAIL and E2E_PASSWORD for authenticated identity proof.')
  test.setTimeout(120_000)

  const fixture = await seedTaskAndRunFixtures(String(testInfo.project.use.baseURL ?? 'http://localhost:8080'))
  expect(fixture).not.toBeNull()
  await loginAsE2EOperator(page)

  for (const width of [480, 768, 1024, 1280] as const) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto(`/tasks/${fixture!.taskId}`, { waitUntil: 'networkidle' })

    const shippedBidi = page.locator('bdi[dir="auto"]')
    expect(await shippedBidi.count()).toBeGreaterThan(1)
    await expect(page.locator('.action-link .app-icon--directional').first()).toBeVisible()
    const email = page.locator('#app-sidebar bdi[dir="auto"]').first()
    expect(await email.textContent()).toBe(process.env.E2E_EMAIL)
    const sidebar = page.locator('#app-sidebar')
    if (width < 1024) {
      await expect(sidebar).toHaveAttribute('inert', '')
      await expect(page.locator('.app-nav-toggle')).toBeVisible()
    } else {
      await expect(sidebar).not.toHaveAttribute('inert', '')
      if (width === 1024) {
        await page.locator('.app-sidebar-close').click()
        await expect(sidebar).toHaveAttribute('inert', '')
        const restore = page.locator('.app-nav-toggle')
        await expect(restore).toBeVisible()
        await expect(restore).toHaveAttribute('aria-label', /restore|restaurar/i)
        await restore.click()
        await expect(sidebar).not.toHaveAttribute('inert', '')
      }
    }

    await injectPseudoLocalization(page, width < 1024 ? 'rtl' : 'ltr')
    const typography = await doubleVisibleTypography(page)
    expect(typography.minFontRatio).toBeCloseTo(2, 5)
    expect(typography.maxFontRatio).toBeCloseTo(2, 5)
    expect(typography.minLineRatio).toBeCloseTo(2, 5)
    expect(typography.maxLineRatio).toBeCloseTo(2, 5)
    await expectBoundedDocument(page)
    await expectNoClippedText(page)
    const controls = await auditInteractiveControls(page, false)
    console.log(`text-resize-authenticated-${width}`, JSON.stringify({ typography, controls }))
  }

  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto(`/runs/${fixture!.runId}`, { waitUntil: 'networkidle' })
  const runTitle = page.locator('h1 bdi[dir="auto"]')
  await expect(runTitle).toHaveText(fixture!.runId)
})
