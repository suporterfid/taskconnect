import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { describe, expect, it } from 'vitest'

const documentPath = resolve(import.meta.dirname, '../../docs/visual-identity.md')
const identity = readFileSync(documentPath, 'utf8')

const semanticTokens = [
  '--color-bg-canvas',
  '--color-bg-surface',
  '--color-bg-elevated',
  '--color-bg-hover',
  '--color-bg-selected',
  '--color-text-primary',
  '--color-text-secondary',
  '--color-text-disabled',
  '--color-text-inverse',
  '--color-text-link',
  '--color-border-default',
  '--color-border-strong',
  '--color-action-primary',
  '--color-action-primary-hover',
  '--color-action-primary-active',
  '--color-action-primary-content',
  '--color-action-primary-subtle',
  '--color-focus-ring',
  '--color-success-fg',
  '--color-success-bg',
  '--color-success-border',
  '--color-warning-fg',
  '--color-warning-bg',
  '--color-warning-border',
  '--color-danger-fg',
  '--color-danger-bg',
  '--color-danger-border',
  '--color-info-fg',
  '--color-info-bg',
  '--color-info-border',
] as const

describe('visual identity adoption document', () => {
  it('is self-contained and records the complete light/dark semantic contract', () => {
    expect(identity).toContain('# TaskConnect Visual Identity Specification')
    expect(identity).toMatch(/WCAG 2\.2 Level AA/)
    expect(identity).toMatch(/digital product UI/i)
    expect(identity).toMatch(/proprietary assets/i)

    for (const token of semanticTokens) {
      const occurrences = identity.match(new RegExp(token, 'g')) ?? []
      expect(occurrences.length, `${token} must be documented`).toBeGreaterThan(0)
    }

    expect(identity).toContain('| `--color-bg-canvas` | `#FFFFFF` | `#191919` |')
    expect(identity).toContain('| `--color-action-primary` | `#1A6DC1` | `#529CCA` |')
    expect(identity).toContain('| `--color-action-primary-content` | `#FFFFFF` | `#111111` |')
  })

  it('defines the shipped theme, i18n, responsive, and accessibility algorithms', () => {
    const requiredContracts = [
      '`taskconnect.theme`',
      '`light | dark | system`',
      '`prefers-color-scheme`',
      '`color-scheme`',
      '`prefers-reduced-motion`',
      '`forced-colors`',
      '`en-XA`',
      '`ar-XB`',
      '2Ã—',
      '30%',
      'Arabic',
      'Hebrew',
      'CJK',
      'Thai',
      'Devanagari',
      'Cyrillic',
      'Greek',
      '720px',
      '1200px',
      '320 CSS px',
      '200%',
      '44Ã—44',
    ]

    for (const contract of requiredContracts) {
      expect(identity, `missing ${contract}`).toContain(contract)
    }
  })

  it('governs every shared component family and the email exception', () => {
    const componentFamilies = [
      'navigation',
      'buttons',
      'form controls',
      'cards',
      'lists',
      'database tables',
      'tags',
      'callouts',
      'menus',
      'dialogs',
      'tooltips',
      'toasts',
      'skeletons',
      'empty',
      'loading',
      'error',
    ]

    for (const family of componentFamilies) {
      expect(identity.toLowerCase(), `missing ${family}`).toContain(family)
    }

    expect(identity).toMatch(/transactional email/i)
    expect(identity).toMatch(/static contrast/i)
    expect(identity).toMatch(/Design handoff checklist/)
    expect(identity).toMatch(/Engineering handoff checklist/)
    expect(identity).toMatch(/Token governance/)
  })
})
