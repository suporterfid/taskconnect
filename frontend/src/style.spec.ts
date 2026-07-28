import { readdirSync, readFileSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

import { describe, expect, it } from 'vitest'

const sourceRoot = dirname(fileURLToPath(import.meta.url))
const rawPaletteUtility =
  /(?<![\w-])(?:bg|text|border|ring|divide|outline)-(?:gray|slate|zinc|violet|purple|indigo)-\d{2,3}(?![\w-])/g

// This inventory is intentional technical debt. Migration issues remove entries; new
// raw-palette utilities must never be added. Keeping counts per token also prevents a
// legacy utility from being silently exchanged for a different raw palette value.
const legacyAllowlist: Record<string, Record<string, number>> = {
  'src/pages/ApiKeysPage.vue': { 'bg-gray-50': 2, 'bg-gray-900': 2, 'bg-gray-950': 1, 'bg-violet-600': 2, 'bg-violet-700': 2, 'border-gray-200': 3, 'border-gray-300': 6, 'border-gray-700': 1, 'border-gray-800': 2, 'divide-gray-200': 2, 'divide-gray-800': 2, 'text-gray-100': 1, 'text-gray-500': 12, 'text-gray-600': 6, 'text-gray-700': 7, 'text-gray-900': 1, 'text-violet-600': 2 },
  'src/pages/AuditLogsPage.vue': { 'bg-gray-50': 1, 'bg-gray-900': 1, 'bg-gray-950': 1, 'border-gray-200': 1, 'border-gray-300': 2, 'border-gray-800': 1, 'divide-gray-200': 2, 'divide-gray-800': 2, 'text-gray-400': 2, 'text-gray-500': 8, 'text-gray-600': 2 },
  'src/pages/EndpointProfileDetailPage.vue': { 'bg-gray-50': 1, 'bg-gray-900': 4, 'bg-gray-950': 1, 'bg-violet-600': 2, 'bg-violet-700': 2, 'border-gray-100': 1, 'border-gray-200': 2, 'border-gray-300': 2, 'border-gray-800': 3, 'text-gray-100': 1, 'text-gray-200': 1, 'text-gray-500': 19, 'text-gray-700': 2, 'text-gray-800': 1, 'text-gray-900': 1, 'text-violet-600': 1 },
  'src/pages/EndpointProfileFormPage.vue': { 'bg-gray-50': 2, 'bg-gray-900': 1, 'bg-violet-600': 1, 'bg-violet-700': 1, 'border-gray-100': 1, 'border-gray-200': 1, 'border-gray-300': 15, 'border-gray-800': 1, 'text-gray-500': 1, 'text-gray-600': 1, 'text-gray-700': 16, 'text-violet-600': 2 },
  'src/pages/EndpointProfileListPage.vue': { 'bg-gray-100': 1, 'bg-gray-50': 1, 'bg-gray-800': 1, 'bg-gray-900': 1, 'bg-gray-950': 1, 'bg-violet-600': 1, 'bg-violet-700': 1, 'border-gray-200': 1, 'border-gray-300': 2, 'border-gray-800': 1, 'divide-gray-200': 2, 'divide-gray-800': 2, 'text-gray-200': 1, 'text-gray-500': 8, 'text-gray-600': 1, 'text-gray-700': 1, 'text-violet-600': 5 },
  'src/pages/EnvironmentsPage.vue': { 'bg-gray-100': 1, 'bg-gray-50': 2, 'bg-gray-800': 1, 'bg-gray-900': 2, 'bg-gray-950': 1, 'bg-violet-600': 2, 'bg-violet-700': 2, 'border-gray-200': 2, 'border-gray-300': 5, 'border-gray-800': 2, 'divide-gray-200': 2, 'divide-gray-800': 2, 'text-gray-100': 1, 'text-gray-300': 1, 'text-gray-500': 7, 'text-gray-600': 2, 'text-gray-700': 3, 'text-gray-900': 1, 'text-violet-600': 2 },
  'src/pages/MembersPage.vue': { 'bg-gray-100': 1, 'bg-gray-50': 2, 'bg-gray-800': 1, 'bg-gray-900': 2, 'bg-gray-950': 1, 'bg-violet-50': 1, 'bg-violet-600': 2, 'bg-violet-700': 2, 'bg-violet-950': 1, 'border-gray-200': 2, 'border-gray-300': 6, 'border-gray-800': 2, 'divide-gray-200': 2, 'divide-gray-800': 2, 'text-gray-100': 2, 'text-gray-300': 1, 'text-gray-500': 8, 'text-gray-600': 2, 'text-gray-700': 4, 'text-gray-900': 2, 'text-violet-300': 1, 'text-violet-600': 2, 'text-violet-700': 1 },
  'src/pages/PlatformHealthPage.vue': { 'bg-gray-900': 2, 'border-gray-200': 2, 'border-gray-800': 2, 'text-gray-500': 16 },
  'src/pages/SecretsPage.vue': { 'bg-gray-50': 2, 'bg-gray-900': 2, 'bg-gray-950': 2, 'bg-violet-600': 3, 'bg-violet-700': 3, 'border-gray-200': 3, 'border-gray-300': 5, 'border-gray-700': 1, 'border-gray-800': 2, 'divide-gray-200': 2, 'divide-gray-800': 2, 'text-gray-100': 1, 'text-gray-500': 9, 'text-gray-600': 4, 'text-gray-700': 4, 'text-gray-900': 1, 'text-violet-600': 2 },
  'src/pages/SettingsPage.vue': { 'bg-gray-50': 1, 'bg-gray-800': 3, 'bg-gray-900': 5, 'bg-gray-950': 1, 'bg-violet-600': 3, 'bg-violet-700': 3, 'border-gray-200': 6, 'border-gray-300': 6, 'border-gray-700': 3, 'border-gray-800': 6, 'divide-gray-200': 2, 'divide-gray-800': 2, 'text-gray-300': 4, 'text-gray-400': 2, 'text-gray-500': 21, 'text-gray-600': 2, 'text-gray-700': 4, 'text-violet-600': 1 },
  'src/pages/TaskWizardPage.vue': { 'bg-gray-100': 1, 'bg-gray-50': 3, 'bg-gray-800': 3, 'bg-gray-900': 1, 'bg-gray-950': 23, 'bg-violet-600': 4, 'border-gray-200': 3, 'border-gray-300': 24, 'border-gray-700': 24, 'border-gray-800': 3, 'border-violet-600': 1, 'text-gray-300': 1, 'text-gray-400': 2, 'text-gray-500': 5, 'text-gray-600': 4, 'text-gray-700': 1, 'text-violet-300': 1, 'text-violet-600': 3, 'text-violet-700': 1 },
}

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
