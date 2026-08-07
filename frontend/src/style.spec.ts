import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

import { describe, expect, it } from 'vitest'

const sourceRoot = dirname(fileURLToPath(import.meta.url))
const rawPaletteUtility =
  /(?<![\w-])(?:bg|text|border|ring|divide|outline)-(?:gray|slate|zinc|violet|purple|indigo)-\d{2,3}(?![\w-])/g

// This inventory is intentional technical debt. Migration issues remove entries; new
// raw-palette utilities must never be added. Keeping counts per token also prevents a
// legacy utility from being silently exchanged for a different raw palette value.
//
// The migration is complete: every page has moved to semantic tokens, so this
// allowlist is empty and the guard now runs unconditionally.
const legacyAllowlist: Record<string, Record<string, number>> = {}

function vueFiles(directory: string): string[] {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name)
    return entry.isDirectory() ? vueFiles(path) : entry.name.endsWith('.vue') ? [path] : []
  })
}

describe('semantic color token guard', () => {
  it('does not introduce raw palette utilities in Vue components', () => {
    const actual: Record<string, Record<string, number>> = {}

    for (const file of vueFiles(sourceRoot)) {
      const matches = readFileSync(file, 'utf8').match(rawPaletteUtility) ?? []
      if (matches.length === 0) continue

      const fileName = `src/${relative(sourceRoot, file).replaceAll('\\', '/')}`
      actual[fileName] = {}
      for (const utility of matches) {
        actual[fileName][utility] = (actual[fileName][utility] ?? 0) + 1
      }
    }

    expect(actual).toEqual(legacyAllowlist)
  })
})

describe('visual identity foundation', () => {
  const frontendRoot = join(sourceRoot, '..')
  const styleCss = readFileSync(join(sourceRoot, 'style.css'), 'utf8')

  it('loads the external no-flash bootstrap before application rendering in both shells', () => {
    const viteShell = readFileSync(join(frontendRoot, 'index.html'), 'utf8')
    const bladeShell = readFileSync(join(frontendRoot, '..', 'resources', 'views', 'app.blade.php'), 'utf8')

    expect(viteShell).toContain('<script vite-ignore src="%BASE_URL%theme-init.js"></script>')
    expect(viteShell.indexOf('theme-init.js')).toBeLessThan(viteShell.indexOf('/src/main.ts'))
    expect(bladeShell).toContain("asset('build/theme-init.js')")
    expect(bladeShell.indexOf('theme-init.js')).toBeLessThan(bladeShell.indexOf('@foreach ($cssFiles as $css)'))
  })

  it('self-hosts licensed Inter and declares multilingual editorial and code fallbacks', () => {
    expect(existsSync(join(sourceRoot, 'assets', 'fonts', 'inter', 'inter-4.1-variable.woff2'))).toBe(true)
    expect(existsSync(join(sourceRoot, 'assets', 'fonts', 'inter', 'INTER-LICENSE.txt'))).toBe(true)
    expect(styleCss).toContain('font-family: "Inter"')
    expect(styleCss).toContain('font-display: swap')
    expect(styleCss).toContain('"Source Serif 4"')
    expect(styleCss).toContain('"IBM Plex Mono"')
    expect(styleCss).toContain('"Noto Sans Arabic"')
    expect(styleCss).toContain('"Noto Sans Devanagari"')
  })

  it('defines forced-colors and reduced-motion behavior through semantic focus rules', () => {
    expect(styleCss).toMatch(/@media\s*\(forced-colors:\s*active\)/)
    expect(styleCss).toContain('forced-color-adjust: auto')
    expect(styleCss).toMatch(/@media\s*\(prefers-reduced-motion:\s*reduce\)/)
    expect(styleCss).toContain('outline: 2px solid var(--color-focus-ring)')
  })

  it('keeps the complete non-color foundation available to the Laravel CSS entry', () => {
    const resourceCss = readFileSync(join(frontendRoot, '..', 'resources', 'css', 'app.css'), 'utf8')
    const requiredContracts = [
      '--font-editorial:',
      '--font-code:',
      '--space-16: 64px',
      '--control-target-min-size: 44px',
      '--icon-standalone-size: 24px',
      '--text-display-title: 44px',
      '--leading-display-title: 52px',
      '--shadow-0: none',
      '--ease-exit: cubic-bezier(0.4, 0, 1, 1)',
      '--layer-blocking: 60',
    ]

    for (const contract of requiredContracts) expect(resourceCss).toContain(contract)
  })
})
