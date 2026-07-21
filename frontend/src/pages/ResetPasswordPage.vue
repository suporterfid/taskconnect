<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { ApiError, ensureCsrfCookie } from '@/services/api'
import api from '@/services/api'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const token = ref(typeof route.query.token === 'string' ? route.query.token : '')
const password = ref('')
const passwordConfirmation = ref('')
const submitting = ref(false)
const error = ref<string | null>(null)
const success = ref(false)

async function onSubmit(): Promise<void> {
  submitting.value = true
  error.value = null
  success.value = false

  try {
    await ensureCsrfCookie()
    await api.post('/auth/reset-password', {
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    success.value = true
    setTimeout(() => {
      void router.push({ name: 'login' })
    }, 1500)
  } catch (err) {
    error.value =
      err instanceof ApiError ? err.message : t('auth.reset.error')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 dark:bg-gray-950">
    <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
        {{ $t('auth.reset.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ $t('auth.reset.subtitle') }}
      </p>

      <div
        v-if="success"
        class="mt-6 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
        role="status"
      >
        {{ $t('auth.reset.success') }}
      </div>

      <form v-else class="mt-8 space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('auth.reset.email') }}
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

        <div>
          <label for="token" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('auth.reset.token') }}
          </label>
          <input
            id="token"
            v-model="token"
            type="text"
            required
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-800"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('auth.reset.password') }}
          </label>
          <input
            id="password"
            v-model="password"
            type="password"
            required
            autocomplete="new-password"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
          />
        </div>

        <div>
          <label
            for="password_confirmation"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            {{ $t('auth.reset.passwordConfirmation') }}
          </label>
          <input
            id="password_confirmation"
            v-model="passwordConfirmation"
            type="password"
            required
            autocomplete="new-password"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
          />
        </div>

        <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>

        <button
          type="submit"
          :disabled="submitting || !token"
          class="w-full rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
        >
          {{ submitting ? $t('auth.reset.submitting') : $t('auth.reset.submit') }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm">
        <RouterLink to="/login" class="text-violet-600 hover:underline">
          {{ $t('auth.reset.backToLogin') }}
        </RouterLink>
      </p>
    </div>
  </div>
</template>
