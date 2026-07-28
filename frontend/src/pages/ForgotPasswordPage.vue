<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import { ApiError, ensureCsrfCookie } from '@/services/api'
import api from '@/services/api'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

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
  <div class="flex min-h-screen items-center justify-center bg-canvas px-4">
    <div class="w-full max-w-md rounded-xl border border-border bg-surface p-8 shadow-sm">
      <h1>{{ $t('auth.forgot.title') }}</h1>
      <p class="mt-1 text-sm text-muted">
        {{ $t('auth.forgot.subtitle') }}
      </p>

      <BaseAlert v-if="success" tone="success" role="status" class="mt-6">
        {{ $t('auth.forgot.success') }}
      </BaseAlert>

      <form v-else class="mt-8 space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="email" class="block text-sm font-medium text-text">
            {{ $t('auth.forgot.email') }}
          </label>
          <BaseInput id="email" v-model="email" type="email" required autocomplete="email" class="mt-1" />
        </div>

        <BaseAlert v-if="error" tone="danger">{{ error }}</BaseAlert>

        <BaseButton type="submit" class="w-full" :loading="submitting">
          {{ submitting ? $t('auth.forgot.submitting') : $t('auth.forgot.submit') }}
        </BaseButton>
      </form>

      <p class="mt-6 text-center text-sm">
        <RouterLink to="/login" class="link text-action">
          {{ $t('auth.forgot.backToLogin') }}
        </RouterLink>
      </p>
    </div>
  </div>
</template>
