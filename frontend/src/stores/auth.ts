import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import api, { ApiError, ensureCsrfCookie, resetCsrfCookie } from '@/services/api'
import type { User } from '@/services/types'
import { i18n, setLocale, type SupportedLocale, SUPPORTED_LOCALES } from '@/i18n'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const initialized = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => user.value !== null)

  function applyUserPreferences(next: User | null): void {
    const locale = next?.preferences?.locale
    if (locale && SUPPORTED_LOCALES.includes(locale as SupportedLocale)) {
      setLocale(locale as SupportedLocale)
    }
  }

  async function fetchUser(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get<{ data: User }>('/me')
      user.value = data.data ?? (data as unknown as User)
      applyUserPreferences(user.value)
    } catch (err) {
      user.value = null
      if (err instanceof ApiError && err.status !== 401) {
        error.value = err.message
      }
    } finally {
      loading.value = false
      initialized.value = true
    }
  }

  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    error.value = null

    try {
      resetCsrfCookie()
      await ensureCsrfCookie()
      await api.post('/auth/login', { email, password })
      await fetchUser()
    } catch (err) {
      user.value = null
      error.value =
        err instanceof ApiError ? err.message : i18n.global.t('common.errors.loginFailed')
      throw err
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      // Best-effort server logout. 401/419 are expected when the session
      // already expired — always clear local state afterward.
      await ensureCsrfCookie()
      await api.post('/auth/logout')
    } catch {
      // Ignore logout errors — clear local session regardless
    } finally {
      user.value = null
      resetCsrfCookie()
      loading.value = false
    }
  }

  return {
    user,
    loading,
    initialized,
    error,
    isAuthenticated,
    fetchUser,
    login,
    logout,
  }
})
