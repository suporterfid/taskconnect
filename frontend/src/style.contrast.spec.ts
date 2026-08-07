import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { describe, expect, it } from 'vitest'

const styleCssPath = join(dirname(fileURLToPath(import.meta.url)), 'style.css')
const frontendStyleCss = readFileSync(styleCssPath, 'utf8')
const resourceStyleCss = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'resources', 'css', 'app.css'), 'utf8')

const canonicalTokens = {
  light: {
    '--color-bg-canvas': '#FFFFFF',
    '--color-bg-surface': '#F7F6F3',
    '--color-bg-elevated': '#FFFFFF',
    '--color-bg-hover': '#EFEDEA',
    '--color-bg-selected': '#E7F0FA',
    '--color-text-primary': '#252525',
    '--color-text-secondary': '#5F5F5F',
    '--color-text-disabled': '#929292',
    '--color-text-inverse': '#FFFFFF',
    '--color-text-link': '#0F5EAB',
    '--color-border-default': '#D9D7D3',
    '--color-border-strong': '#8A8882',
    '--color-action-primary': '#1A6DC1',
    '--color-action-primary-hover': '#14599E',
    '--color-action-primary-active': '#104B86',
    '--color-action-primary-content': '#FFFFFF',
    '--color-action-primary-subtle': '#E7F0FA',
    '--color-focus-ring': '#1A6DC1',
    '--color-success-fg': '#126B3A',
    '--color-success-bg': '#F1FAF4',
    '--color-success-border': '#7CCB98',
    '--color-warning-fg': '#7A4A00',
    '--color-warning-bg': '#FFF7E6',
    '--color-warning-border': '#F0B35A',
    '--color-danger-fg': '#B42318',
    '--color-danger-bg': '#FFF1F0',
    '--color-danger-border': '#F29A93',
    '--color-info-fg': '#0F5EAB',
    '--color-info-bg': '#EDF5FE',
    '--color-info-border': '#85BCEB',
  },
  dark: {
    '--color-bg-canvas': '#191919',
    '--color-bg-surface': '#202020',
    '--color-bg-elevated': '#252525',
    '--color-bg-hover': '#2C2C2C',
    '--color-bg-selected': '#123B60',
    '--color-text-primary': '#F1F1EF',
    '--color-text-secondary': '#C6C6C2',
    '--color-text-disabled': '#888884',
    '--color-text-inverse': '#191919',
    '--color-text-link': '#79B8E8',
    '--color-border-default': '#4A4A4A',
    '--color-border-strong': '#6E6E6E',
    '--color-action-primary': '#529CCA',
    '--color-action-primary-hover': '#70B4DE',
    '--color-action-primary-active': '#3E83B5',
    '--color-action-primary-content': '#111111',
    '--color-action-primary-subtle': '#173755',
    '--color-focus-ring': '#79B8E8',
    '--color-success-fg': '#7CDA9A',
    '--color-success-bg': '#13291C',
    '--color-success-border': '#34794C',
    '--color-warning-fg': '#F5C775',
    '--color-warning-bg': '#33250D',
    '--color-warning-border': '#8D6418',
    '--color-danger-fg': '#F4A49E',
    '--color-danger-bg': '#381B1B',
    '--color-danger-border': '#8E4540',
    '--color-info-fg': '#9DCCF2',
    '--color-info-bg': '#102B45',
    '--color-info-border': '#3D78AA',
  },
} as const

type Theme = keyof typeof canonicalTokens

function themeBlock(css: string, theme: Theme): string {
  const selector = theme === 'light' ? /:root\s*,\s*\[data-theme=['"]light['"]\]\s*\{([\s\S]*?)\}/ : /\[data-theme=['"]dark['"]\]\s*\{([\s\S]*?)\}/
  const match = css.match(selector)
  if (!match) throw new Error(`missing ${theme} theme block`)
  return match[1]
}

function extractTokens(block: string): Record<string, string> {
  return Object.fromEntries(
    [...block.matchAll(/(--color-[\w-]+):\s*(#[0-9a-fA-F]{6});/g)].map((match) => [match[1], match[2].toUpperCase()]),
  )
}

function parseHex(value: string): [number, number, number] {
  const int = Number.parseInt(value.slice(1), 16)
  return [(int >> 16) & 255, (int >> 8) & 255, int & 255]
}

function relativeLuminance(value: string): number {
  const [r, g, b] = parseHex(value).map((channel) => {
    const srgb = channel / 255
    return srgb <= 0.04045 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contrastRatio(foreground: string, background: string): number {
  const foregroundLuminance = relativeLuminance(foreground)
  const backgroundLuminance = relativeLuminance(background)
  const lighter = Math.max(foregroundLuminance, backgroundLuminance)
  const darker = Math.min(foregroundLuminance, backgroundLuminance)
  return (lighter + 0.05) / (darker + 0.05)
}

const textPairs = [
  ['--color-text-primary', '--color-bg-canvas'],
  ['--color-text-primary', '--color-bg-surface'],
  ['--color-text-secondary', '--color-bg-canvas'],
  ['--color-text-secondary', '--color-bg-surface'],
  ['--color-text-link', '--color-bg-canvas'],
  ['--color-text-link', '--color-bg-surface'],
  ['--color-action-primary-content', '--color-action-primary'],
  ['--color-success-fg', '--color-success-bg'],
  ['--color-warning-fg', '--color-warning-bg'],
  ['--color-danger-fg', '--color-danger-bg'],
  ['--color-info-fg', '--color-info-bg'],
] as const

describe.each(['light', 'dark'] as const)('%s semantic color contract', (theme) => {
  const actual = extractTokens(themeBlock(frontendStyleCss, theme))

  it('defines the complete canonical 30-token matrix with exact values', () => {
    expect(actual).toEqual(canonicalTokens[theme])
  })

  it('keeps the Laravel CSS entry aligned with the frontend contract', () => {
    expect(extractTokens(themeBlock(resourceStyleCss, theme))).toEqual(canonicalTokens[theme])
  })

  it.each(textPairs)('%s on %s meets 4.5:1', (foreground, background) => {
    expect(contrastRatio(actual[foreground], actual[background])).toBeGreaterThanOrEqual(4.5)
  })

  it('provides a 3:1 strong control boundary and focus indicator', () => {
    expect(contrastRatio(actual['--color-border-strong'], actual['--color-bg-canvas'])).toBeGreaterThanOrEqual(3)
    expect(contrastRatio(actual['--color-focus-ring'], actual['--color-bg-canvas'])).toBeGreaterThanOrEqual(3)
  })
})
