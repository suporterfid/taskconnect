import { existsSync, readFileSync, readdirSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
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

function normalizedLinkElements(source: string): string[] {
  return [...source.matchAll(/<(?:RouterLink|a)\b[\s\S]*?>/g)].map(([element]) =>
    element.replace(/\s+/g, ' ').trim(),
  )
}

function hasMinimumActionTarget(element: string): boolean {
  const classes = element.match(/\bclass="([^"]*)"/)?.[1]?.split(/\s+/) ?? []
  return (
    classes.includes('action-link') ||
    classes.includes('primary-action') ||
    (classes.includes('min-h-11') && classes.includes('min-w-11'))
  )
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

  it('gives destructive actions a strong outer boundary plus the complete danger triplet', () => {
    expect(button).toContain('destructive-action')
    expect(style).toMatch(/\.destructive-action\s*\{[\s\S]*border:[^;]*color-border-strong/)
    expect(style).toMatch(/\.destructive-action\s*\{[\s\S]*color-danger-border/)
    expect(style).toMatch(/\.destructive-action\s*\{[\s\S]*color-danger-bg/)
    expect(style).toMatch(/\.destructive-action\s*\{[\s\S]*color-danger-fg/)
  })

  it('uses a strong essential boundary and a non-color invalid cue for every field control', () => {
    for (const control of [input, select, textarea]) {
      expect(control).toContain('border-border-strong')
      expect(control).toContain('invalid-control')
      expect(control).toContain('min-w-0')
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
  const style = read('style.css')

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

  it('gives every standalone navigation element a 44px action contract', () => {
    const exemptInlineOrTableLinks = new Map<string, number>([
      // Inline data links in dashboard list rows.
      ['pages/DashboardPage.vue::<RouterLink :to="`/runs/${run.id}`" class="link text-sm font-medium text-action-text">', 1],
      ['pages/DashboardPage.vue::<RouterLink :to="`/tasks/${task.id}`" class="link text-sm font-medium text-action-text">', 1],
      // Links contained by data-table cells.
      ['pages/DlqPage.vue::<RouterLink :to="`/runs/${run.id}`" class="link text-action-text" data-testid="dlq-run-link" >', 1],
      ['pages/DlqPage.vue::<RouterLink v-if="run.task_id" :to="`/tasks/${run.task_id}`" class="link text-action-text">', 1],
      ['pages/DlqPage.vue::<RouterLink :to="`/runs/${run.id}`" class="link text-action-text" data-testid="dlq-inspect">', 1],
      ['pages/EndpointProfileListPage.vue::<RouterLink :to="`/endpoint-profiles/${profile.id}`" class="link font-medium text-action-text">', 1],
      ['pages/EndpointProfileListPage.vue::<RouterLink :to="`/endpoint-profiles/${profile.id}`" class="link text-action-text">', 1],
      ['pages/EndpointProfileListPage.vue::<RouterLink :to="`/endpoint-profiles/${profile.id}/edit`" class="link text-action-text">', 1],
      ['pages/PipelineDetailPage.vue::<RouterLink v-if="node.task_id" :to="`/tasks/${node.task_id}`" class="link text-action-text">', 1],
      ['pages/PipelineDetailPage.vue::<RouterLink v-if="node.task_run_id" :to="`/runs/${node.task_run_id}`" class="link text-action-text">', 1],
      ['pages/PipelineListPage.vue::<RouterLink :to="`/pipelines/${instance.template_name}/instances/${instance.id}`" class="link text-action-text" data-testid="pipeline-instance-link" >', 1],
      ['pages/RunListPage.vue::<RouterLink v-if="run.task_id" :to="`/tasks/${run.task_id}`" class="link text-action-text">', 1],
      ['pages/RunListPage.vue::<RouterLink :to="`/runs/${run.id}`" class="link text-action-text">', 1],
      ['pages/TaskListPage.vue::<RouterLink :to="`/tasks/${task.id}`" class="link text-action-text">', 2],
      // Inline prose and definition-value links.
      ['pages/RunDetailPage.vue::<RouterLink v-if="data.run.task_id" :to="`/tasks/${data.run.task_id}`" class="link text-action-text">', 1],
      ['pages/RunListPage.vue::<RouterLink :to="`/tasks/${taskIdFilter}`" class="link text-action-text">', 1],
      ['pages/RunListPage.vue::<RouterLink to="/runs" class="link text-action-text">', 1],
    ])
    const exemptionUse = new Map<string, number>()
    const violations: string[] = []

    for (const file of vueFiles(join(sourceRoot, 'pages'))) {
      const page = `pages/${relative(join(sourceRoot, 'pages'), file).replaceAll('\\', '/')}`
      for (const element of normalizedLinkElements(readFileSync(file, 'utf8'))) {
        if (hasMinimumActionTarget(element)) continue
        const signature = `${page}::${element}`
        const used = (exemptionUse.get(signature) ?? 0) + 1
        exemptionUse.set(signature, used)
        if (used > (exemptInlineOrTableLinks.get(signature) ?? 0)) violations.push(signature)
      }
    }

    expect(violations).toEqual([])
    expect(exemptionUse).toEqual(exemptInlineOrTableLinks)
  })

  it('reflows all key/value editor rows with logical min-width and wrap-safe actions', () => {
    const endpointEditor = read('pages/EndpointProfileFormPage.vue')
    const taskEditor = read('pages/TaskWizardPage.vue')

    expect(endpointEditor.match(/class="key-value-row"/g)).toHaveLength(1)
    expect(taskEditor.match(/class="key-value-row"/g)).toHaveLength(2)
    for (const source of [endpointEditor, taskEditor]) {
      expect(source).not.toContain('class="w-1/3"')
      expect(source).toContain('class="min-w-0"')
    }
    expect(style).toMatch(/\.key-value-row\s*\{[\s\S]*minmax\(0, 1fr\)[\s\S]*minmax\(0, 2fr\)/)
    expect(style).toMatch(/@media\s*\(max-width:\s*479px\)[\s\S]*\.key-value-row\s*\{[\s\S]*grid-template-columns:\s*minmax\(0, 1fr\)/)
  })

  it('keeps the narrow top bar in flow and removes the desktop focus offset there', () => {
    const layout = read('layouts/AppLayout.vue')
    const narrow = layout.match(/@media \(max-width: 479px\) \{([\s\S]*?)\n\}/)?.[1] ?? ''

    expect(narrow).toMatch(/\.app-header\s*\{[\s\S]*position:\s*static/)
    expect(narrow).toMatch(/\.app-main :global\(:focus\)\s*\{[\s\S]*scroll-margin-block:\s*var\(--space-4\)/)
    expect(narrow).not.toContain('--space-16')
  })

  it('exposes the full values previously truncated in data tables', () => {
    const inventory = [
      ['pages/ApiKeysPage.vue', 'permissionsLabel(key.permissions'],
      ['pages/EndpointProfileListPage.vue', 'profile.base_url'],
      ['pages/TaskListPage.vue', 'scheduleLabel(task)'],
    ] as const

    for (const [file, value] of inventory) {
      const source = read(file)
      const line = source.split('\n').findIndex((candidate) => candidate.includes(value))
      expect(line, `${file} contains ${value}`).toBeGreaterThan(0)
      expect(source.split('\n').slice(Math.max(0, line - 2), line + 2).join('\n')).not.toContain('truncate')
      expect(source.split('\n').slice(Math.max(0, line - 2), line + 2).join('\n')).toMatch(/break-(?:words|all)/)
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
