<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { useTenantStore } from '@/stores/tenant'
import type { SupportedLocale } from '@/i18n'

const route = useRoute()
const { t } = useI18n()
const auth = useAuthStore()
const tenant = useTenantStore()
const localeStore = useLocaleStore()

const navItems = computed(() => {
  const items = [
    { name: 'dashboard', label: t('common.nav.dashboard'), to: '/dashboard' },
    { name: 'tasks', label: t('common.nav.tasks'), to: '/tasks' },
    { name: 'dlq', label: t('common.nav.dlq'), to: '/dlq' },
    { name: 'pipelines', label: t('common.nav.pipelines'), to: '/pipelines' },
    {
      name: 'endpoint-profiles',
      label: t('common.nav.endpointProfiles'),
      to: '/endpoint-profiles',
    },
    { name: 'secrets', label: t('common.nav.secrets'), to: '/secrets' },
    { name: 'runs', label: t('common.nav.runs'), to: '/runs' },
    {
      name: 'environments',
      label: t('common.nav.environments'),
      to: '/environments',
    },
    { name: 'api-keys', label: t('common.nav.apiKeys'), to: '/api-keys' },
    { name: 'members', label: t('common.nav.members'), to: '/members' },
    { name: 'audit-logs', label: t('common.nav.auditLogs'), to: '/audit-logs' },
    { name: 'settings', label: t('common.nav.settings'), to: '/settings' },
  ]

  if (auth.user?.is_platform_admin) {
    items.push({
      name: 'platform-health',
      label: t('common.nav.platformHealth'),
      to: '/platform-health',
    })
  }

  return items
})

function isActive(path: string): boolean {
  return route.path === path || route.path.startsWith(`${path}/`)
}

async function onLogout(): Promise<void> {
  await auth.logout()
  // Full navigation avoids stale-tab chunk load failures after deploys.
  window.location.assign('/login')
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
  <div class="app-shell flex min-h-screen bg-canvas">
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:text-text"
    >
      {{ $t('common.skipToContent') }}
    </a>

    <aside class="flex w-64 shrink-0 flex-col border-r border-border bg-surface">
      <div class="border-b border-border px-4 py-5">
        <RouterLink to="/dashboard" class="text-lg font-semibold text-action">
          {{ $t('common.appName') }}
        </RouterLink>
        <p v-if="auth.user" class="mt-1 truncate text-sm text-muted">
          {{ auth.user.email }}
        </p>
      </div>

      <nav class="flex-1 space-y-1 p-3" :aria-label="$t('common.navMain')">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          :aria-current="isActive(item.to) ? 'page' : undefined"
          class="block rounded-md border-l-4 px-3 py-2 text-sm transition-colors duration-standard ease-standard"
          :class="
            isActive(item.to)
              ? 'border-action bg-surface-emphasis font-medium text-text'
              : 'border-transparent text-muted hover:bg-surface-emphasis hover:text-text'
          "
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="border-t border-border p-3">
        <button
          type="button"
          class="w-full rounded-md px-3 py-2 text-left text-sm text-muted hover:bg-surface-emphasis hover:text-text"
          @click="onLogout"
        >
          {{ $t('common.nav.logout') }}
        </button>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex flex-wrap items-center gap-4 border-b border-border bg-surface px-6 py-3">
        <label class="flex items-center gap-2 text-sm">
          <span class="text-muted">{{ $t('common.tenant.label') }}</span>
          <select
            class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-text"
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
          <span class="text-muted">{{ $t('common.environment.label') }}</span>
          <select
            class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-text"
            :value="tenant.currentEnvironmentId ?? ''"
            @change="onEnvironmentChange"
          >
            <option v-if="tenant.activeEnvironments.length === 0" value="">
              {{ $t('common.environment.select') }}
            </option>
            <option
              v-for="env in tenant.activeEnvironments"
              :key="env.id"
              :value="env.id"
            >
              {{ env.name }}
            </option>
          </select>
        </label>

        <label class="ml-auto flex items-center gap-2 text-sm">
          <span class="text-muted">{{ $t('common.locale.label') }}</span>
          <select
            class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-text"
            :value="localeStore.currentLocale"
            @change="onLocaleChange"
          >
            <option value="en">{{ $t('common.locale.en') }}</option>
            <option value="pt-BR">{{ $t('common.locale.pt-BR') }}</option>
          </select>
        </label>
      </header>

      <main id="main-content" class="flex-1 p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
