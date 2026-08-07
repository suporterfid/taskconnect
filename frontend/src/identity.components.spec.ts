import { existsSync, readFileSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { describe, expect, it } from 'vitest'

const sourceRoot = dirname(fileURLToPath(import.meta.url))
const frontendRoot = join(sourceRoot, '..')

function read(relativePath: string): string {
  const path = join(sourceRoot, relativePath)
  return existsSync(path) ? readFileSync(path, 'utf8') : ''
}

function vueFiles(directory: string): string[] {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name)
    return entry.isDirectory() ? vueFiles(path) : entry.name.endsWith('.vue') ? [path] : []
  })
}

describe('Task 3 semantic component contracts', () => {
  const style = read('style.css')
  const button = read('components/ui/BaseButton.vue')
  const alert = read('components/ui/BaseAlert.vue')
  const badge = read('components/ui/BaseBadge.vue')
  const input = read('components/ui/BaseInput.vue')
  const select = read('components/ui/BaseSelect.vue')
  const textarea = read('components/ui/BaseTextarea.vue')
  const table = read('components/ui/table.ts')

  it('uses explicit semantic status triplets without opacity-generated colors', () => {
    for (const source of [alert, badge]) {
      expect(source).not.toMatch(/(?:success|warning|danger|info)\/\d+/)
      for (const tone of ['success', 'warning', 'danger', 'info']) {
        expect(source).toContain(`--color-${tone}-border`)
        expect(source).toContain(`--color-${tone}-bg`)
        expect(source).toContain(`--color-${tone}-fg`)
      }
    }
  })

  it('uses semantic action content and provides wrap-safe 44px actions in every size', () => {
    expect(button).toContain('--color-action-primary')
    expect(button).toContain('--color-action-primary-content')
    expect(button).not.toContain('text-white')
    expect(button.match(/min-h-11/g)).toHaveLength(2)
    expect(button.match(/min-w-11/g)).toHaveLength(2)
    expect(button).toContain('whitespace-normal')
    expect(button).toContain('break-words')
    expect(style).toContain('@utility action-link')
    expect(style).toMatch(/@utility action-link[\s\S]*min-block-size:\s*var\(--control-target-min-size\)/)
  })

  it('uses a strong essential boundary and a non-color invalid cue for every field control', () => {
    for (const control of [input, select, textarea]) {
      expect(control).toContain('border-border-strong')
      expect(control).toContain('invalid-control')
      expect(control).not.toContain("'border-danger'")
      expect(control).not.toContain("'border-border'")
    }
    expect(style).toMatch(/@utility invalid-control[\s\S]*color-danger-fg/)
  })

  it('defines shared reading, wide-data, page-header, and focusable table-scroll contracts', () => {
    expect(style).toContain('@utility page-header')
    expect(style).toContain('@utility table-scroll')
    expect(style).toContain('--container-reading')
    expect(style).toContain('--container-app')
    expect(table).toContain("role: 'region'")
    expect(table).toContain('tabindex: 0')
    expect(table).toContain('table-scroll')
    expect(table).toContain('labelled horizontal-scroll region')
  })

  it('keeps named existing state patterns semantic in forced colors and reduced motion', () => {
    const loading = read('components/LoadingState.vue')
    const error = read('components/ErrorState.vue')
    const empty = read('components/ui/EmptyState.vue')

    expect(loading).toContain('loading-indicator')
    expect(error).toContain('status-danger')
    expect(empty).toContain('empty-state')
    expect(style).toMatch(/@media\s*\(forced-colors:\s*active\)[\s\S]*\.table-scroll/)
    expect(style).toMatch(/@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*\.skeleton/)
  })
})

describe('Task 3 source-wide migration guard', () => {
  it('does not bypass semantic colors or logical direction in product Vue surfaces', () => {
    const forbidden = [
      /\btext-white\b/,
      /\b(?:bg|border)-(?:success|warning|danger|info)\/\d+\b/,
      /\b(?:ml|mr|pl|pr)-\S+/,
      /\btext-(?:left|right)\b/,
      /\bspace-x-\S+/,
    ]

    for (const file of vueFiles(sourceRoot)) {
      const source = readFileSync(file, 'utf8')
      for (const pattern of forbidden) expect(source, `${file} matched ${pattern}`).not.toMatch(pattern)
    }
  })

  it('makes every existing data table a localized keyboard-focusable scroll region', () => {
    const pages = vueFiles(join(sourceRoot, 'pages'))
      .map((file) => ({ file, source: readFileSync(file, 'utf8') }))
      .filter(({ source }) => source.includes('<table'))

    expect(pages).toHaveLength(12)
    for (const { file, source } of pages) {
      expect(source, file).toContain('class="table-scroll"')
      expect(source, file).toContain('role="region"')
      expect(source, file).toContain('tabindex="0"')
      expect(source, file).toContain("$t('common.table.scrollRegion')")
    }
  })

  it('keeps the independent server error shell warm-neutral and system-aware', () => {
    const errorShell = readFileSync(
      join(frontendRoot, '..', 'resources', 'views', 'errors', 'minimal.blade.php'),
      'utf8',
    )

    expect(errorShell).toContain("lang=\"{{ str_replace('_', '-', app()->getLocale()) }}\"")
    expect(errorShell).toContain('@media (prefers-color-scheme: dark)')
    expect(errorShell).toContain('--color-bg-canvas: #FFFFFF')
    expect(errorShell).toContain('--color-bg-canvas: #191919')
    expect(errorShell).toContain('min-block-size: 44px')
    expect(errorShell).toContain("{{ __('errors.back') }}")
    expect(errorShell).not.toMatch(/#000000|#814dde|#1f0d69|#ebebeb|#b0b0b0/i)
  })
})
