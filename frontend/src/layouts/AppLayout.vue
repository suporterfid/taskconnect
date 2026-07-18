<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { useTenantStore } from '@/stores/tenant'
import type { SupportedLocale } from '@/i18n'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const tenant = useTenantStore()
const localeStore = useLocaleStore()

const navItems = computed(() => [
  { name: 'dashboard', label: t('common.nav.dashboard'), to: '/dashboard' },
  { name: 'tasks', label: t('common.nav.tasks'), to: '/tasks' },
  {
    name: 'endpoint-profiles',
    label: t('common.nav.endpointProfiles'),
    to: '/endpoint-profiles',
  },
  { name: 'runs', label: t('common.nav.runs'), to: '/runs' },
  {
    name: 'environments',
    label: t('common.nav.environments'),
    to: '/environments',
  },
  { name: 'api-keys', label: t('common.nav.apiKeys'), to: '/api-keys' },
  { name: 'members', label: t('common.nav.members'), to: '/members' },
  { name: 'settings', label: t('common.nav.settings'), to: '/settings' },
  {
    name: 'platform-health',
    label: t('common.nav.platformHealth'),
    to: '/platform-health',
  },
])

function isActive(path: string): boolean {
  return route.path === path || route.path.startsWith(`${path}/`)
}

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}

function onTenantChange(event: Event): void {
  const id = (event.target as HTMLSelectElement).value
  if (id) {
    void tenant.setTenant(id)
  }
}

function onEnvironmentChange(event: Event): void {
  const id = (event.target as HTMLSelectElement).value
  if (id) {
    tenant.setEnvironment(id)
  }
}

function onLocaleChange(event: Event): void {
  const locale = (event.target as HTMLSelectElement).value as SupportedLocale
  localeStore.switchLocale(locale)
}
</script>

<template>
  <div class="flex min-h-screen bg-gray-50 dark:bg-gray-950">
    <aside
      class="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
      <div class="border-b border-gray-200 px-4 py-5 dark:border-gray-800">
        <RouterLink to="/dashboard" class="text-lg font-semibold text-violet-600">
          {{ $t('common.appName') }}
        </RouterLink>
        <p v-if="auth.user" class="mt-1 truncate text-xs text-gray-500">
          {{ auth.user.email }}
        </p>
      </div>

      <nav class="flex-1 space-y-1 p-3" aria-label="Main">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          class="block rounded-md px-3 py-2 text-sm transition-colors"
          :class="
            isActive(item.to)
              ? 'bg-violet-50 font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-300'
              : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
          "
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="border-t border-gray-200 p-3 dark:border-gray-800">
        <button
          type="button"
          class="w-full rounded-md px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
          @click="onLogout"
        >
          {{ $t('common.nav.logout') }}
        </button>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="flex flex-wrap items-center gap-4 border-b border-gray-200 bg-white px-6 py-3 dark:border-gray-800 dark:bg-gray-900"
      >
        <label class="flex items-center gap-2 text-sm">
          <span class="text-gray-500">{{ $t('common.tenant.label') }}</span>
          <select
            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
            :value="tenant.currentTenantId ?? ''"
            @change="onTenantChange"
          >
            <option v-if="tenant.tenants.length === 0" value="">
              {{ $t('common.tenant.select') }}
            </option>
            <option v-for="tnt in tenant.tenants" :key="tnt.id" :value="tnt.id">
              {{ tnt.name }}
            </option>
          </select>
        </label>

        <label class="flex items-center gap-2 text-sm">
          <span class="text-gray-500">{{ $t('common.environment.label') }}</span>
          <select
            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
            :value="tenant.currentEnvironmentId ?? ''"
            @change="onEnvironmentChange"
          >
            <option v-if="tenant.environments.length === 0" value="">
              {{ $t('common.environment.select') }}
            </option>
            <option v-for="env in tenant.environments" :key="env.id" :value="env.id">
              {{ env.name }}
            </option>
          </select>
        </label>

        <label class="ml-auto flex items-center gap-2 text-sm">
          <span class="text-gray-500">{{ $t('common.locale.label') }}</span>
          <select
            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
            :value="localeStore.currentLocale"
            @change="onLocaleChange"
          >
            <option value="en">{{ $t('common.locale.en') }}</option>
            <option value="pt-BR">{{ $t('common.locale.pt-BR') }}</option>
          </select>
        </label>
      </header>

      <main class="flex-1 p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
