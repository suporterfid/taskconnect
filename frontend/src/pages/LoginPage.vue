<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { ApiError } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const submitting = ref(false)
const error = ref<string | null>(null)

async function onSubmit(): Promise<void> {
  submitting.value = true
  error.value = null

  try {
    await auth.login(email.value, password.value)
    const redirect = (route.query.redirect as string) || '/dashboard'
    await router.push(redirect)
  } catch (err) {
    error.value =
      err instanceof ApiError
        ? err.message
        : t('auth.errors.invalidCredentials')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-canvas px-4">
    <div class="w-full max-w-md rounded-xl border border-border bg-surface p-8 shadow-sm">
      <h1>{{ $t('auth.login.title') }}</h1>
      <p class="mt-1 text-sm text-muted">
        {{ $t('auth.login.subtitle') }}
      </p>

      <form class="mt-8 space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="email" class="block text-sm font-medium text-text">
            {{ $t('auth.login.email') }}
          </label>
          <BaseInput id="email" v-model="email" type="email" required autocomplete="email" class="mt-1" />
        </div>

        <div>
          <div class="flex items-center justify-between">
            <label for="password" class="block text-sm font-medium text-text">
              {{ $t('auth.login.password') }}
            </label>
            <RouterLink to="/forgot-password" class="action-link text-xs">
              {{ $t('auth.login.forgotPassword') }}
            </RouterLink>
          </div>
          <BaseInput
            id="password"
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="mt-1"
          />
        </div>

        <BaseAlert v-if="error" tone="danger">{{ error }}</BaseAlert>

        <BaseButton type="submit" class="w-full" :loading="submitting">
          {{ submitting ? $t('auth.login.submitting') : $t('auth.login.submit') }}
        </BaseButton>
      </form>
    </div>
  </div>
</template>
