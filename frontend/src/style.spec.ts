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
