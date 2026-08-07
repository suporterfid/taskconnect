import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const layoutSource = readFileSync(
  join(dirname(fileURLToPath(import.meta.url)), 'AppLayout.vue'),
  'utf8',
)

describe('AppLayout structural directionality contract', () => {
  it('does not use physical directional Tailwind utilities', () => {
    const forbidden = [
      /(?:^|[\s:'"])(?:left|right)-\S+/,
      /(?:^|[\s:'"])(?:ml|mr|pl|pr)-\S+/,
      /(?:^|[\s:'"])(?:border-(?:l|r))(?:-|\b)/,
      /(?:^|[\s:'"])(?:text-(?:left|right))(?:\s|'|")/,
      /(?:^|[\s:'"])(?:-?translate-x-\S+)/,
    ]

    for (const pattern of forbidden) {
      expect(layoutSource, `physical utility matched ${pattern}`).not.toMatch(pattern)
    }
  })

  it('maps asymmetric safe areas and closes the sidebar toward logical inline-start', () => {
    expect(layoutSource).toContain('--safe-inline-start: env(safe-area-inset-left)')
    expect(layoutSource).toContain('--safe-inline-end: env(safe-area-inset-right)')
    expect(layoutSource).toContain(":global([dir='rtl']) .app-shell")
    expect(layoutSource).toContain('--safe-inline-start: env(safe-area-inset-right)')
    expect(layoutSource).toContain('inset-inline-start: 0')
    expect(layoutSource).toContain('border-inline-end')
    expect(layoutSource).toContain(":global([dir='rtl']) .app-sidebar--closed")
  })

  it('uses one localized theme control and 44px shell targets', () => {
    expect(layoutSource).toContain('<ThemeSelect')
    expect(layoutSource.match(/<ThemeSelect/g)).toHaveLength(1)
    expect(layoutSource).toContain('min-h-11')
    expect(layoutSource).toContain('min-w-11')
  })
})
