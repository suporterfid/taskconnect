import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { describe, expect, it } from 'vitest'

// Codifies docs/visual-identity-spec.md §3.4: every documented foreground/background
// pair must keep its measured contrast ratio. Reads the actual `--color-*` custom
// properties out of style.css (not a hardcoded copy) so an edit to a token's palette
// value breaks this test immediately, without needing a browser — see #98.

const styleCssPath = join(dirname(fileURLToPath(import.meta.url)), 'style.css')
const styleCss = readFileSync(styleCssPath, 'utf8')

function extractColorTokens(css: string): Record<string, string> {
  const raw: Record<string, string> = {}
  const declRe = /(--color-[\w-]+):\s*([^;]+);/g
  for (const match of css.matchAll(declRe)) {
    raw[match[1]] = match[2].trim()
  }

  const resolved: Record<string, string> = {}
  function resolve(name: string, seen: Set<string> = new Set()): string {
    if (resolved[name]) {
      return resolved[name]
    }
    if (seen.has(name)) {
      throw new Error(`circular --color token reference: ${name}`)
    }
    const value = raw[name]
    if (!value) {
      throw new Error(`unknown --color token: ${name}`)
    }
    const varMatch = value.match(/^var\((--color-[\w-]+)\)$/)
    const result = varMatch ? resolve(varMatch[1], new Set(seen).add(name)) : value
    resolved[name] = result
    return result
  }

  for (const name of Object.keys(raw)) {
    resolve(name)
  }
  return resolved
}

const tokens = extractColorTokens(styleCss)

function parseColor(value: string): [number, number, number] {
  const hex = value.match(/^#([0-9a-fA-F]{6})$/)
  if (hex) {
    const int = parseInt(hex[1], 16)
    return [(int >> 16) & 255, (int >> 8) & 255, int & 255]
  }
  const rgb = value.match(/^rgb\((\d+)\s+(\d+)\s+(\d+)/)
  if (rgb) {
    return [Number(rgb[1]), Number(rgb[2]), Number(rgb[3])]
  }
  throw new Error(`unsupported color format: ${value}`)
}

// WCAG 2.x relative luminance + contrast ratio (https://www.w3.org/TR/WCAG21/#dfn-relative-luminance).
function relativeLuminance([r, g, b]: [number, number, number]): number {
  const channel = (c: number) => {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
  }
  const [rl, gl, bl] = [channel(r), channel(g), channel(b)]
  return 0.2126 * rl + 0.7152 * gl + 0.0722 * bl
}

function contrastRatio(a: string, b: string): number {
  const la = relativeLuminance(parseColor(tokens[a] ?? a))
  const lb = relativeLuminance(parseColor(tokens[b] ?? b))
  const lighter = Math.max(la, lb)
  const darker = Math.min(la, lb)
  return (lighter + 0.05) / (darker + 0.05)
}

const NORMAL_TEXT_MIN = 4.5

// Each pair is a real foreground/background combination shipped in the app today.
// `label` documents where it's used so a failure points straight at the component.
const pairs: Array<{ label: string; fg: string; bg: string; min: number }> = [
  { label: 'body text on canvas', fg: '--color-text', bg: '--color-canvas', min: NORMAL_TEXT_MIN },
  { label: 'body text on surface (cards)', fg: '--color-text', bg: '--color-surface', min: NORMAL_TEXT_MIN },
  {
    label: 'body text on surface-emphasis (active nav / bulk banners)',
    fg: '--color-text',
    bg: '--color-surface-emphasis',
    min: NORMAL_TEXT_MIN,
  },
  { label: 'muted text on canvas', fg: '--color-text-muted', bg: '--color-canvas', min: NORMAL_TEXT_MIN },
  { label: 'muted text on surface', fg: '--color-text-muted', bg: '--color-surface', min: NORMAL_TEXT_MIN },
  {
    label: 'muted text on surface-emphasis',
    fg: '--color-text-muted',
    bg: '--color-surface-emphasis',
    min: NORMAL_TEXT_MIN,
  },
  {
    label: 'white text on BaseButton primary fill',
    fg: '--color-neutral-0',
    bg: '--color-action',
    min: NORMAL_TEXT_MIN,
  },
  {
    label: 'white text on BaseButton danger fill',
    fg: '--color-neutral-0',
    bg: '--color-danger-strong',
    min: NORMAL_TEXT_MIN,
  },
  { label: 'success tone on canvas', fg: '--color-success', bg: '--color-canvas', min: NORMAL_TEXT_MIN },
  { label: 'success tone on surface', fg: '--color-success', bg: '--color-surface', min: NORMAL_TEXT_MIN },
  { label: 'warning tone on canvas', fg: '--color-warning', bg: '--color-canvas', min: NORMAL_TEXT_MIN },
  { label: 'warning tone on surface', fg: '--color-warning', bg: '--color-surface', min: NORMAL_TEXT_MIN },
  { label: 'danger tone on canvas', fg: '--color-danger', bg: '--color-canvas', min: NORMAL_TEXT_MIN },
  { label: 'danger tone on surface', fg: '--color-danger', bg: '--color-surface', min: NORMAL_TEXT_MIN },
  { label: 'info tone on canvas', fg: '--color-info', bg: '--color-canvas', min: NORMAL_TEXT_MIN },
  { label: 'info tone on surface', fg: '--color-info', bg: '--color-surface', min: NORMAL_TEXT_MIN },
]

describe('§3.4 token contrast table', () => {
  it.each(pairs)('$label meets $min:1', ({ fg, bg, min }) => {
    const ratio = contrastRatio(fg, bg)
    expect(ratio).toBeGreaterThanOrEqual(min)
  })

  // Known, tracked gap — see #118. `--color-action` (the `text-action` link color) was
  // never previously contrast-checked (login.a11y.spec.ts disables axe's color-contrast
  // rule for jsdom) and measures below 4.5:1 for normal text on both backgrounds it's
  // used against. Fixing it means introducing and measuring a new text-only token and
  // repointing ~22 files, which is a brand-color decision for a maintainer to sign off
  // on — out of scope for "verify and document" (#98). `it.fails` keeps this visible
  // (a silent `.skip` would not) instead of quietly passing or breaking the suite.
  it.fails('#118: action links on canvas currently fail 4.5:1 (measures 4.05:1)', () => {
    expect(contrastRatio('--color-action', '--color-canvas')).toBeGreaterThanOrEqual(NORMAL_TEXT_MIN)
  })
  it.fails('#118: action links on surface currently fail 4.5:1 (measures 3.50:1)', () => {
    expect(contrastRatio('--color-action', '--color-surface')).toBeGreaterThanOrEqual(NORMAL_TEXT_MIN)
  })
})
