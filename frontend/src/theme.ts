import type { InjectionKey } from 'vue'

export const THEME_STORAGE_KEY = 'taskconnect.theme'

export type ThemePreference = 'light' | 'dark' | 'system'
export type ResolvedTheme = Exclude<ThemePreference, 'system'>

type StorageLike = Pick<Storage, 'getItem' | 'setItem'>
type ThemeMediaQuery = Pick<MediaQueryList, 'matches' | 'addEventListener' | 'removeEventListener'>

export interface ThemeController {
  readonly preference: ThemePreference | null
  readonly resolved: ResolvedTheme
  setPreference(preference: ThemePreference): void
  destroy(): void
}

export const THEME_CONTROLLER_KEY: InjectionKey<ThemeController> = Symbol(
  'taskconnect-theme-controller',
)

interface ThemeControllerOptions {
  document: Document
  media: ThemeMediaQuery
  storage: StorageLike | null
}

const isThemePreference = (value: string | null): value is ThemePreference =>
  value === 'light' || value === 'dark' || value === 'system'

export function readThemePreference(storage: StorageLike | null): ThemePreference | null {
  try {
    const value = storage?.getItem(THEME_STORAGE_KEY) ?? null
    return isThemePreference(value) ? value : null
  } catch {
    return null
  }
}

function resolveTheme(preference: ThemePreference | null, media: ThemeMediaQuery): ResolvedTheme {
  if (preference === 'light' || preference === 'dark') return preference
  return media.matches ? 'dark' : 'light'
}

function applyTheme(document: Document, theme: ResolvedTheme): void {
  const root = document.documentElement
  root.dataset.theme = theme
  root.classList.toggle('dark', theme === 'dark')
  root.style.colorScheme = theme

  let themeColor = document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')
  if (!themeColor) {
    themeColor = document.createElement('meta')
    themeColor.name = 'theme-color'
    document.head.append(themeColor)
  }
  themeColor.content = theme === 'dark' ? '#191919' : '#FFFFFF'
}

export function createThemeController(options: ThemeControllerOptions): ThemeController {
  let preference = readThemePreference(options.storage)
  let resolved = resolveTheme(preference, options.media)

  const sync = () => {
    resolved = resolveTheme(preference, options.media)
    applyTheme(options.document, resolved)
  }
  const handleMediaChange = () => {
    if (preference === null || preference === 'system') sync()
  }

  options.media.addEventListener('change', handleMediaChange)
  sync()

  return {
    get preference() {
      return preference
    },
    get resolved() {
      return resolved
    },
    setPreference(nextPreference) {
      preference = nextPreference
      try {
        options.storage?.setItem(THEME_STORAGE_KEY, nextPreference)
      } catch {
        // Storage can be denied by browser privacy policy; the in-memory choice still applies.
      }
      sync()
    },
    destroy() {
      options.media.removeEventListener('change', handleMediaChange)
    },
  }
}

let defaultController: ThemeController | null = null

export function initializeTheme(): ThemeController {
  if (defaultController) return defaultController

  let storage: StorageLike | null = null
  try {
    storage = window.localStorage
  } catch {
    // Access itself can throw in restricted browsing contexts.
  }

  defaultController = createThemeController({
    document,
    media: window.matchMedia('(prefers-color-scheme: dark)'),
    storage,
  })
  return defaultController
}
