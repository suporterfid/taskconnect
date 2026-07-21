<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import { ApiError, ensureCsrfCookie } from '@/services/api'
import api from '@/services/api'

const { t } = useI18n()

const email = ref('')
const submitting = ref(false)
const error = ref<string | null>(null)
const success = ref(false)

async function onSubmit(): Promise<void> {
  submitting.value = true
  error.value = null
  success.value = false

  try {
    await ensureCsrfCookie()
    await api.post('/auth/forgot-password', { email: email.value })
    success.value = true
  } catch (err) {
    error.value =
      err instanceof ApiError ? err.message : t('auth.forgot.error')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 dark:bg-gray-950">
    <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
        {{ $t('auth.forgot.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ $t('auth.forgot.subtitle') }}
      </p>

      <div
        v-if="success"
        class="mt-6 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
        role="status"
      >
        {{ $t('auth.forgot.success') }}
      </div>

      <form v-else class="mt-8 space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('auth.forgot.email') }}
          </label>
          <input
            id="email"
            v-model="email"
            type="email"
            required
            autocomplete="email"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
          />
        </div>

        <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>

        <button
          type="submit"
          :disabled="submitting"
          class="w-full rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
        >
          {{ submitting ? $t('auth.forgot.submitting') : $t('auth.forgot.submit') }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm">
        <RouterLink to="/login" class="text-violet-600 hover:underline">
          {{ $t('auth.forgot.backToLogin') }}
        </RouterLink>
      </p>
    </div>
  </div>
</template>
