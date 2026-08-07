import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createThemeController, readThemePreference, THEME_STORAGE_KEY, type ThemePreference } from './theme'

class MediaQueryStub extends EventTarget {
  matches: boolean
  readonly media = '(prefers-color-scheme: dark)'

  constructor(matches: boolean) {
    super()
    this.matches = matches
  }

  setDark(matches: boolean) {
    this.matches = matches
    this.dispatchEvent(new Event('change'))
  }
}

function createStorage(initial?: string, throws = false): Storage {
  let value = initial ?? null
  return {
    getItem: vi.fn(() => {
      if (throws) throw new DOMException('blocked')
      return value
    }),
    setItem: vi.fn((_key: string, next: string) => {
      if (throws) throw new DOMException('blocked')
      value = next
    }),
    removeItem: vi.fn(),
    clear: vi.fn(),
    key: vi.fn(),
    get length() {
      return value === null ? 0 : 1
    },
  }
}

describe('theme preference runtime', () => {
  beforeEach(() => {
    document.documentElement.removeAttribute('data-theme')
    document.documentElement.removeAttribute('style')
    document.documentElement.classList.remove('dark')
    document.head.innerHTML = '<meta name="theme-color" content="#000000">'
  })

  it.each([
    ['light', 'light'],
    ['dark', 'dark'],
    ['system', 'system'],
    ['sepia', null],
    ['', null],
  ] as const)('accepts only the exact persisted value %s', (stored, expected) => {
    expect(readThemePreference(createStorage(stored))).toBe(expected)
  })

  it('falls back safely when storage access is blocked', () => {
    expect(readThemePreference(createStorage(undefined, true))).toBeNull()
  })

  it.each([
    ['light', true, 'light'],
    ['dark', false, 'dark'],
    ['system', true, 'dark'],
    ['system', false, 'light'],
    [undefined, true, 'dark'],
    [undefined, false, 'light'],
  ] as Array<[ThemePreference | undefined, boolean, 'light' | 'dark']>)(
    'resolves persisted %s with OS dark=%s to %s before rendering',
    (stored, osDark, expected) => {
      const media = new MediaQueryStub(osDark)
      const storage = createStorage(stored)
      const controller = createThemeController({ document, media, storage })

      expect(controller.resolved).toBe(expected)
      expect(document.documentElement.dataset.theme).toBe(expected)
      expect(document.documentElement.style.colorScheme).toBe(expected)
      expect(document.documentElement.classList.contains('dark')).toBe(expected === 'dark')
      expect(document.querySelector('meta[name="theme-color"]')?.getAttribute('content')).toBe(
        expected === 'dark' ? '#191919' : '#FFFFFF',
      )

      controller.destroy()
    },
  )

  it('updates with OS changes only for system or absent preference', () => {
    const media = new MediaQueryStub(false)
    const controller = createThemeController({ document, media, storage: createStorage() })

    media.setDark(true)
    expect(controller.resolved).toBe('dark')
    controller.setPreference('light')
    expect(localPreference(controller.preference)).toBe('light')
    media.setDark(false)
    media.setDark(true)
    expect(controller.resolved).toBe('light')
    controller.setPreference('system')
    expect(controller.resolved).toBe('dark')

    controller.destroy()
  })

  it('persists explicit choices under the canonical key and survives write failures', () => {
    const storage = createStorage()
    const controller = createThemeController({ document, media: new MediaQueryStub(false), storage })
    controller.setPreference('dark')
    expect(storage.setItem).toHaveBeenCalledWith(THEME_STORAGE_KEY, 'dark')
    controller.destroy()

    const blockedController = createThemeController({
      document,
      media: new MediaQueryStub(false),
      storage: createStorage(undefined, true),
    })
    expect(() => blockedController.setPreference('dark')).not.toThrow()
    expect(blockedController.resolved).toBe('dark')
    blockedController.destroy()
  })
})

function localPreference(value: ThemePreference | null): ThemePreference | null {
  return value
}
