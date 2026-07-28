<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { ApiError, ensureCsrfCookie } from '@/services/api'
import api from '@/services/api'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

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
  <div class="flex min-h-screen items-center justify-center bg-canvas px-4">
    <div class="w-full max-w-md rounded-xl border border-border bg-surface p-8 shadow-sm">
      <h1>{{ $t('auth.reset.title') }}</h1>
      <p class="mt-1 text-sm text-muted">
        {{ $t('auth.reset.subtitle') }}
      </p>

      <BaseAlert v-if="success" tone="success" role="status" class="mt-6">
        {{ $t('auth.reset.success') }}
      </BaseAlert>

      <form v-else class="mt-8 space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="email" class="block text-sm font-medium text-text">
            {{ $t('auth.reset.email') }}
          </label>
          <BaseInput id="email" v-model="email" type="email" required autocomplete="email" class="mt-1" />
        </div>

        <div>
          <label for="token" class="block text-sm font-medium text-text">
            {{ $t('auth.reset.token') }}
          </label>
          <BaseInput id="token" v-model="token" type="text" required class="mt-1 font-mono" />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-text">
            {{ $t('auth.reset.password') }}
          </label>
          <!-- Password rules readable before submission, not only after an error. -->
          <p id="password-hint" class="mt-1 mb-1 text-sm text-muted">
            {{ $t('auth.reset.passwordHint') }}
          </p>
          <BaseInput
            id="password"
            v-model="password"
            type="password"
            required
            autocomplete="new-password"
            described-by="password-hint"
          />
        </div>

        <div>
          <label for="password_confirmation" class="block text-sm font-medium text-text">
            {{ $t('auth.reset.passwordConfirmation') }}
          </label>
          <BaseInput
            id="password_confirmation"
            v-model="passwordConfirmation"
            type="password"
            required
            autocomplete="new-password"
            class="mt-1"
          />
        </div>

        <BaseAlert v-if="error" tone="danger">{{ error }}</BaseAlert>

        <BaseButton type="submit" class="w-full" :loading="submitting" :disabled="!token">
          {{ submitting ? $t('auth.reset.submitting') : $t('auth.reset.submit') }}
        </BaseButton>
      </form>

      <p class="mt-6 text-center text-sm">
        <RouterLink to="/login" class="link text-action">
          {{ $t('auth.reset.backToLogin') }}
        </RouterLink>
      </p>
    </div>
  </div>
</template>
