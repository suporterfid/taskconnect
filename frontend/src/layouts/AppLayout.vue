<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core'
import { Menu, MoreHorizontal, PanelLeftOpen, X } from 'lucide-vue-next'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import BidiText from '@/components/ui/BidiText.vue'
import ThemeSelect from '@/components/ui/ThemeSelect.vue'
import type { SupportedLocale } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { useTenantStore } from '@/stores/tenant'

const route = useRoute()
const { t } = useI18n()
const auth = useAuthStore()
const tenant = useTenantStore()
const localeStore = useLocaleStore()

const mobileNavOpen = ref(false)
const sidebarCollapsed = ref(false)
const headerOverflowOpen = ref(false)
const navTrigger = ref<HTMLButtonElement | null>(null)
const overlaySidebarMode = useMediaQuery('(max-width: 1023px)')
const sidebarVisible = computed(() =>
  overlaySidebarMode.value ? mobileNavOpen.value : !sidebarCollapsed.value,
)
const sidebarSuppressed = computed(() => !sidebarVisible.value)
const wideDataRouteNames = new Set([
  'api-keys', 'audit-logs', 'dlq', 'endpoint-profiles', 'environments',
  'members', 'pipelines', 'pipelines-detail', 'runs', 'secrets', 'settings', 'tasks',
])
const isWideDataView = computed(() => wideDataRouteNames.has(String(route.name ?? '')))

watch(
  () => route.path,
  () => {
    mobileNavOpen.value = false
    headerOverflowOpen.value = false
  },
)

function openNavigation(event: Event): void {
  navTrigger.value = event.currentTarget as HTMLButtonElement
  if (overlaySidebarMode.value) mobileNavOpen.value = true
  else sidebarCollapsed.value = false
}

async function closeNavigation(): Promise<void> {
  if (overlaySidebarMode.value) mobileNavOpen.value = false
  else sidebarCollapsed.value = true
  await nextTick()
  navTrigger.value?.focus()
}

const navItems = computed(() => {
  const items = [
    { name: 'dashboard', label: t('common.nav.dashboard'), to: '/dashboard' },
    { name: 'tasks', label: t('common.nav.tasks'), to: '/tasks' },
    { name: 'dlq', label: t('common.nav.dlq'), to: '/dlq' },
    { name: 'pipelines', label: t('common.nav.pipelines'), to: '/pipelines' },
    { name: 'endpoint-profiles', label: t('common.nav.endpointProfiles'), to: '/endpoint-profiles' },
    { name: 'secrets', label: t('common.nav.secrets'), to: '/secrets' },
    { name: 'runs', label: t('common.nav.runs'), to: '/runs' },
    { name: 'environments', label: t('common.nav.environments'), to: '/environments' },
    { name: 'api-keys', label: t('common.nav.apiKeys'), to: '/api-keys' },
    { name: 'members', label: t('common.nav.members'), to: '/members' },
    { name: 'audit-logs', label: t('common.nav.auditLogs'), to: '/audit-logs' },
    { name: 'settings', label: t('common.nav.settings'), to: '/settings' },
  ]
  if (auth.user?.is_platform_admin) {
    items.push({ name: 'platform-health', label: t('common.nav.platformHealth'), to: '/platform-health' })
  }
  return items
})

function isActive(path: string): boolean {
  return route.path === path || route.path.startsWith(`${path}/`)
}

async function onLogout(): Promise<void> {
  await auth.logout()
  window.location.assign('/login')
}

function onTenantChange(event: Event): void {
  const id = (event.target as HTMLSelectElement).value
  if (id) void tenant.setTenant(id)
}

function onEnvironmentChange(event: Event): void {
  const id = (event.target as HTMLSelectElement).value
  if (id) tenant.setEnvironment(id)
}

function onLocaleChange(event: Event): void {
  void localeStore.persistLocale((event.target as HTMLSelectElement).value as SupportedLocale)
}
</script>

<template>
  <div class="app-shell flex min-h-screen bg-canvas">
    <a href="#main-content" class="skip-link sr-only focus:not-sr-only focus:fixed focus:top-2 focus:start-2 focus:min-h-11 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:text-text">
      {{ $t('common.skipToContent') }}
    </a>

    <div v-if="overlaySidebarMode && mobileNavOpen" class="app-scrim fixed inset-0 bg-canvas/70" @click="closeNavigation" />

    <aside
      id="app-sidebar"
      class="app-sidebar fixed flex shrink-0 flex-col border-border bg-surface transition-transform duration-standard ease-standard"
      :class="sidebarVisible ? 'app-sidebar--open' : 'app-sidebar--closed'"
      :inert="sidebarSuppressed ? true : undefined"
      :aria-hidden="sidebarSuppressed ? 'true' : undefined"
      @keydown.escape="closeNavigation"
    >
      <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-5">
        <div class="min-w-0">
          <RouterLink to="/dashboard" class="inline-flex min-h-11 items-center text-lg font-semibold text-action-text">
            {{ $t('common.appName') }}
          </RouterLink>
          <p v-if="auth.user" class="mt-1 break-all text-sm text-muted">
            <BidiText :value="auth.user.email" />
          </p>
        </div>
        <button type="button" class="app-sidebar-close min-h-11 min-w-11 shrink-0 rounded-md p-2 text-muted hover:bg-surface-emphasis hover:text-text" :aria-label="$t('common.navToggle.close')" @click="closeNavigation">
          <AppIcon :icon="X" :size="20" />
        </button>
      </div>

      <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto p-3" :aria-label="$t('common.navMain')" tabindex="0">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          :aria-current="isActive(item.to) ? 'page' : undefined"
          class="nav-item flex min-h-11 items-center rounded-md px-3 py-2 text-sm transition-colors duration-standard ease-standard"
          :class="isActive(item.to) ? 'border-action font-medium text-text' : 'border-transparent text-muted hover:bg-surface-emphasis hover:text-text'"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="border-t border-border p-3">
        <button type="button" class="min-h-11 w-full rounded-md px-3 py-2 text-start text-sm text-muted hover:bg-surface-emphasis hover:text-text" @click="onLogout">
          {{ $t('common.nav.logout') }}
        </button>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="app-header flex flex-wrap items-center gap-4 border-b border-border bg-surface">
        <button
          type="button"
          class="app-nav-toggle min-h-11 min-w-11 rounded-md p-2 text-muted hover:bg-surface-emphasis hover:text-text"
          :aria-label="$t(sidebarCollapsed ? 'common.navToggle.restore' : 'common.navToggle.open')"
          aria-controls="app-sidebar"
          :aria-expanded="sidebarVisible"
          @click="openNavigation"
        >
          <AppIcon :icon="sidebarCollapsed ? PanelLeftOpen : Menu" :size="20" :directional="true" />
        </button>

        <button
          type="button"
          class="topbar-overflow-toggle min-h-11 min-w-11 rounded-md p-2 text-muted hover:bg-surface-emphasis hover:text-text"
          :aria-label="$t(headerOverflowOpen ? 'common.topbarToggle.close' : 'common.topbarToggle.open')"
          aria-controls="app-topbar-secondary"
          :aria-expanded="headerOverflowOpen"
          @click="headerOverflowOpen = !headerOverflowOpen"
        >
          <AppIcon :icon="MoreHorizontal" :size="20" />
        </button>

        <div id="app-topbar-secondary" class="app-header-secondary min-w-0 flex-1 flex-wrap items-center gap-4" :class="headerOverflowOpen ? 'app-header-secondary--open' : undefined">
          <label class="flex min-w-0 flex-wrap items-center gap-2 text-sm">
            <span class="text-muted">{{ $t('common.tenant.label') }}</span>
            <select class="min-h-11 max-w-full min-w-0 rounded-md border border-border-strong bg-surface px-2 py-1 text-sm text-text" :value="tenant.currentTenantId ?? ''" @change="onTenantChange">
              <option v-if="tenant.tenants.length === 0" value="">{{ $t('common.tenant.select') }}</option>
              <option v-for="tnt in tenant.tenants" :key="tnt.id" :value="tnt.id">{{ tnt.name }}</option>
            </select>
          </label>

          <label class="flex min-w-0 flex-wrap items-center gap-2 text-sm">
            <span class="text-muted">{{ $t('common.environment.label') }}</span>
            <select class="min-h-11 max-w-full min-w-0 rounded-md border border-border-strong bg-surface px-2 py-1 text-sm text-text" :value="tenant.currentEnvironmentId ?? ''" @change="onEnvironmentChange">
              <option v-if="tenant.activeEnvironments.length === 0" value="">{{ $t('common.environment.select') }}</option>
              <option v-for="env in tenant.activeEnvironments" :key="env.id" :value="env.id">{{ env.name }}</option>
            </select>
          </label>

          <label class="ms-auto flex min-w-0 flex-wrap items-center gap-2 text-sm">
            <span class="text-muted">{{ $t('common.locale.label') }}</span>
            <select class="min-h-11 max-w-full min-w-0 rounded-md border border-border-strong bg-surface px-2 py-1 text-sm text-text" :value="localeStore.currentLocale" @change="onLocaleChange">
              <option value="en">{{ $t('common.locale.en') }}</option>
              <option value="pt-BR">{{ $t('common.locale.pt-BR') }}</option>
            </select>
          </label>
          <ThemeSelect />
        </div>
      </header>

      <main id="main-content" class="app-main flex-1" :class="{ 'app-main--wide': isWideDataView }">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  --safe-inline-start: env(safe-area-inset-left);
  --safe-inline-end: env(safe-area-inset-right);
  --safe-block-start: env(safe-area-inset-top);
  --safe-block-end: env(safe-area-inset-bottom);
}

:global([dir='rtl'] .app-shell) {
  --safe-inline-start: env(safe-area-inset-right);
  --safe-inline-end: env(safe-area-inset-left);
}

.skip-link { z-index: var(--layer-blocking); }
.app-scrim { z-index: var(--layer-sidebar); }

.app-sidebar {
  box-sizing: border-box;
  inset-block: 0;
  inset-inline-start: 0;
  inline-size: 16rem;
  border-inline-end-width: 1px;
  padding-block-start: var(--safe-block-start);
  padding-block-end: var(--safe-block-end);
  padding-inline-start: var(--safe-inline-start);
  transform: translateX(0);
  z-index: var(--layer-sidebar);
}

.app-sidebar--closed { transform: translateX(-100%); }
:global([dir='rtl'] .app-sidebar--closed) { transform: translateX(100%); }

.nav-item {
  min-inline-size: 0;
  border-inline-start: 4px solid transparent;
  white-space: normal;
  overflow-wrap: normal;
}

.nav-item[aria-current='page'] { background: var(--color-bg-selected); }

.app-header {
  position: sticky;
  inset-block-start: 0;
  padding-block-start: max(var(--space-3), var(--safe-block-start));
  padding-block-end: var(--space-3);
  padding-inline-start: max(var(--space-6), var(--safe-inline-start));
  padding-inline-end: max(var(--space-6), var(--safe-inline-end));
  z-index: var(--layer-header);
}

.app-main {
  box-sizing: border-box;
  inline-size: 100%;
  max-inline-size: var(--container-reading);
  min-inline-size: 0;
  margin-inline: auto;
  padding-block-start: var(--space-6);
  padding-block-end: max(var(--space-6), var(--safe-block-end));
  padding-inline-start: max(var(--space-6), var(--safe-inline-start));
  padding-inline-end: max(var(--space-6), var(--safe-inline-end));
}

.app-main--wide { max-inline-size: var(--container-app); }
.app-main :global(:focus) { scroll-margin-block: calc(var(--space-16) + var(--safe-block-start)); }
.topbar-overflow-toggle { display: none; }
.app-header-secondary { display: flex; }

@media (max-width: 479px) {
  .app-header {
    position: static;
    gap: var(--space-2);
    padding-inline-start: max(var(--space-4), var(--safe-inline-start));
    padding-inline-end: max(var(--space-4), var(--safe-inline-end));
  }
  .topbar-overflow-toggle { display: inline-flex; }
  .app-header-secondary { display: none; flex-basis: 100%; padding-block: var(--space-2); }
  .app-header-secondary--open { display: flex; }
  .app-header-secondary > :global(label),
  .app-header-secondary > :global([data-theme-select]) { flex: 1 1 100%; }
  .app-main {
    padding-inline-start: max(var(--space-4), var(--safe-inline-start));
    padding-inline-end: max(var(--space-4), var(--safe-inline-end));
  }
  .app-main :global(:focus) { scroll-margin-block: var(--space-4); }
}

@media (min-width: 480px) and (max-width: 767px) {
  .app-header { position: static; }
  .app-main {
    padding-inline-start: max(var(--space-5), var(--safe-inline-start));
    padding-inline-end: max(var(--space-5), var(--safe-inline-end));
  }
  .app-main :global(:focus) { scroll-margin-block: var(--space-5); }
}

@media (min-width: 768px) and (max-width: 1023px) {
  .app-main {
    padding-inline-start: max(var(--space-6), var(--safe-inline-start));
    padding-inline-end: max(var(--space-6), var(--safe-inline-end));
  }
}

@media (min-width: 1024px) and (max-width: 1279px) {
  .app-sidebar, .app-sidebar--open { position: static; transform: none; }
  .app-sidebar--closed { inline-size: 0; overflow: hidden; border: 0; padding: 0; transform: none; }
}

@media (min-width: 1280px) {
  .app-sidebar, .app-sidebar--open { position: static; transform: none; }
  .app-sidebar--closed { inline-size: 0; overflow: hidden; border: 0; padding: 0; transform: none; }
}

@media (max-width: 1023px) { .app-sidebar { position: fixed; } }
@media (min-width: 1024px) { .app-nav-toggle[aria-expanded='true'] { display: none; } }
</style>
