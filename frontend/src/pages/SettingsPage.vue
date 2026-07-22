<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import type { SupportedLocale } from '@/i18n'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { AuditLog, RetentionSettings, Tenant, User } from '@/services/types'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { useTenantStore } from '@/stores/tenant'

const { t } = useI18n()
const auth = useAuthStore()
const localeStore = useLocaleStore()
const tenant = useTenantStore()

const note = ref<string | null>(null)
const saveError = ref<string | null>(null)
const saving = ref(false)
const timezone = ref(auth.user?.preferences?.timezone ?? 'UTC')
const failureEmailsEnabled = ref(
  auth.user?.preferences?.failure_emails_enabled ?? true,
)
const allowHostsText = ref('')
const allowHostsNote = ref<string | null>(null)
const allowHostsError = ref<string | null>(null)
const savingAllowHosts = ref(false)

const canLoadAudit = computed(() => Boolean(tenant.currentTenantId))
const canEditAllowHosts = computed(() => Boolean(tenant.currentTenantId))

function syncAllowHostsFromTenant(): void {
  const hosts = tenant.currentTenant?.outbound_allow_hosts ?? []
  allowHostsText.value = hosts.join('\n')
}

watch(
  () => tenant.currentTenantId,
  () => {
    syncAllowHostsFromTenant()
    allowHostsNote.value = null
    allowHostsError.value = null
  },
  { immediate: true },
)

watch(
  () => tenant.currentTenant?.outbound_allow_hosts,
  () => syncAllowHostsFromTenant(),
)

const {
  data: auditLogs,
  loading: auditLoading,
  error: auditError,
  reload: reloadAudit,
} = useAsyncData(async () => {
  if (!tenant.currentTenantId) {
    return [] as AuditLog[]
  }

  const { data: response } = await api.get<{ data: AuditLog[] }>(
    `/tenants/${tenant.currentTenantId}/audit-logs`,
  )
  return response.data ?? []
})

const {
  data: retention,
  loading: retentionLoading,
  error: retentionError,
  reload: reloadRetention,
} = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: RetentionSettings }>(
    '/platform/retention',
  )
  return response.data
})

async function onLocaleChange(event: Event): Promise<void> {
  const locale = (event.target as HTMLSelectElement).value as SupportedLocale
  localeStore.switchLocale(locale)
  await persistPreferences({ locale })
}

async function onTimezoneSave(): Promise<void> {
  await persistPreferences({ timezone: timezone.value.trim() || 'UTC' })
}

async function onFailureEmailsSave(): Promise<void> {
  await persistPreferences({
    failure_emails_enabled: failureEmailsEnabled.value,
  })
}

async function persistPreferences(payload: {
  locale?: SupportedLocale
  timezone?: string
  failure_emails_enabled?: boolean
}): Promise<void> {
  saving.value = true
  saveError.value = null
  note.value = null

  try {
    const { data } = await api.patch<{ data: User }>('/me/preferences', payload)
    auth.user = data.data
    if (data.data.preferences?.locale) {
      localeStore.switchLocale(data.data.preferences.locale as SupportedLocale)
    }
    if (data.data.preferences?.timezone) {
      timezone.value = data.data.preferences.timezone
    }
    failureEmailsEnabled.value =
      data.data.preferences?.failure_emails_enabled ?? true
    note.value = t('settings.saved')
  } catch (err) {
    saveError.value =
      err instanceof ApiError ? err.message : t('settings.saveError')
    if (payload.locale) {
      localeStore.switchLocale(payload.locale)
      note.value = t('settings.locale.savedLocally')
    }
  } finally {
    saving.value = false
  }
}

async function onSaveAllowHosts(): Promise<void> {
  if (!tenant.currentTenantId) {
    return
  }

  savingAllowHosts.value = true
  allowHostsError.value = null
  allowHostsNote.value = null

  const hosts = allowHostsText.value
    .split('\n')
    .map((line) => line.trim().toLowerCase())
    .filter((line) => line !== '')

  try {
    const { data } = await api.patch<{ data: Tenant }>(
      `/tenants/${tenant.currentTenantId}`,
      { outbound_allow_hosts: hosts },
    )
    const index = tenant.tenants.findIndex((item) => item.id === data.data.id)
    if (index >= 0) {
      tenant.tenants[index] = {
        ...tenant.tenants[index],
        ...data.data,
      }
    }
    syncAllowHostsFromTenant()
    allowHostsNote.value = t('settings.outboundAllowHosts.saved')
  } catch (err) {
    allowHostsError.value =
      err instanceof ApiError
        ? err.message
        : t('settings.outboundAllowHosts.saveError')
  } finally {
    savingAllowHosts.value = false
  }
}

function formatWhen(value?: string | null): string {
  if (!value) {
    return '—'
  }
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return value
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('settings.title')" :subtitle="$t('settings.subtitle')" />

    <div class="space-y-6">
      <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-medium">{{ $t('settings.sections.locale') }}</h2>
        <p class="mt-2 text-sm text-gray-500">
          {{ $t('settings.locale.hint') }}
        </p>

        <label class="mt-4 flex max-w-xs flex-col gap-1 text-sm">
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ $t('common.locale.label') }}</span>
          <select
            class="rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
            :value="localeStore.currentLocale"
            :disabled="saving"
            @change="onLocaleChange"
          >
            <option value="en">{{ $t('common.locale.en') }}</option>
            <option value="pt-BR">{{ $t('common.locale.pt-BR') }}</option>
          </select>
        </label>

        <label class="mt-4 flex max-w-xs flex-col gap-1 text-sm">
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ $t('settings.timezone.label') }}</span>
          <input
            v-model="timezone"
            type="text"
            class="rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
            :disabled="saving"
          />
        </label>
        <button
          type="button"
          class="mt-3 rounded-md bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-50"
          :disabled="saving"
          @click="onTimezoneSave"
        >
          {{ $t('settings.timezone.save') }}
        </button>

        <p v-if="note" class="mt-3 text-sm text-green-700" role="status">
          {{ note }}
        </p>
        <p v-if="saveError" class="mt-3 text-sm text-red-600" role="alert">
          {{ saveError }}
        </p>
      </section>

      <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-medium">
          {{ $t('settings.notifications.title') }}
        </h2>
        <p class="mt-2 text-sm text-gray-500">
          {{ $t('settings.notifications.hint') }}
        </p>

        <label class="mt-4 inline-flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input
            v-model="failureEmailsEnabled"
            type="checkbox"
            class="mt-0.5 rounded"
            :disabled="saving"
          />
          <span>
            <span class="font-medium">{{ $t('settings.notifications.failureEmails') }}</span>
            <span class="mt-1 block text-gray-500">
              {{ $t('settings.notifications.failureEmailsHint') }}
            </span>
          </span>
        </label>
        <button
          type="button"
          class="mt-3 rounded-md bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-50"
          :disabled="saving"
          @click="onFailureEmailsSave"
        >
          {{ $t('settings.notifications.save') }}
        </button>
      </section>

      <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-medium">
          {{ $t('settings.outboundAllowHosts.title') }}
        </h2>
        <p class="mt-2 text-sm text-gray-500">
          {{ $t('settings.outboundAllowHosts.hint') }}
        </p>

        <p
          v-if="!canEditAllowHosts"
          class="mt-4 rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500"
        >
          {{ $t('settings.outboundAllowHosts.needsTenant') }}
        </p>
        <template v-else>
          <label class="mt-4 block text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">
              {{ $t('settings.outboundAllowHosts.label') }}
            </span>
            <textarea
              v-model="allowHostsText"
              rows="5"
              class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-800"
              :disabled="savingAllowHosts"
              :placeholder="$t('settings.outboundAllowHosts.placeholder')"
            />
          </label>
          <button
            type="button"
            class="mt-3 rounded-md bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-50"
            :disabled="savingAllowHosts"
            @click="onSaveAllowHosts"
          >
            {{ $t('settings.outboundAllowHosts.save') }}
          </button>
          <p
            v-if="allowHostsNote"
            class="mt-3 text-sm text-green-700"
            role="status"
          >
            {{ allowHostsNote }}
          </p>
          <p
            v-if="allowHostsError"
            class="mt-3 text-sm text-red-600"
            role="alert"
          >
            {{ allowHostsError }}
          </p>
        </template>
      </section>

      <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-medium">{{ $t('settings.retention.title') }}</h2>
        <p class="mt-2 text-sm text-gray-500">
          {{ $t('settings.retention.subtitle') }}
        </p>
        <p class="mt-1 text-sm text-gray-500">
          {{ $t('settings.retention.hint') }}
        </p>

        <LoadingState v-if="retentionLoading" />
        <ErrorState
          v-else-if="retentionError"
          :message="retentionError ?? $t('settings.retention.loadError')"
          @retry="reloadRetention"
        />
        <dl
          v-else-if="retention"
          class="mt-4 grid gap-3 sm:grid-cols-2"
        >
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.payloadSnapshotsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ retention.payload_snapshots_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.attemptMetadataDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ retention.attempt_metadata_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.runSummaryDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ retention.run_summary_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.auditLogsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ retention.audit_logs_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.apiIdempotencyHours') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ retention.api_idempotency_hours }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.systemHeartbeatDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ retention.system_heartbeat_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.deadRunsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ retention.dead_runs_days ?? '—' }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-medium">{{ $t('settings.audit.title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ $t('settings.audit.subtitle') }}</p>
          </div>
          <RouterLink
            v-if="canLoadAudit"
            to="/audit-logs"
            class="text-sm text-violet-600 hover:underline"
          >
            {{ $t('settings.audit.viewAll') }}
          </RouterLink>
        </div>

        <p
          v-if="!canLoadAudit"
          class="rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500"
        >
          {{ $t('settings.audit.needsTenant') }}
        </p>
        <LoadingState v-else-if="auditLoading" />
        <ErrorState
          v-else-if="auditError"
          :message="auditError ?? $t('settings.audit.loadError')"
          @retry="reloadAudit"
        />
        <p
          v-else-if="!auditLogs?.length"
          class="rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500"
        >
          {{ $t('settings.audit.empty') }}
        </p>
        <div v-else class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-800">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  {{ $t('settings.audit.fields.when') }}
                </th>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  {{ $t('settings.audit.fields.action') }}
                </th>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  {{ $t('settings.audit.fields.resource') }}
                </th>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  {{ $t('settings.audit.fields.actor') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
              <tr v-for="log in auditLogs.slice(0, 8)" :key="log.id">
                <td class="px-3 py-2 text-sm">{{ formatWhen(log.created_at) }}</td>
                <td class="px-3 py-2 text-sm font-medium">{{ log.action }}</td>
                <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">
                  {{ log.resource_type }}
                  <span v-if="log.resource_id"> · {{ log.resource_id }}</span>
                </td>
                <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">
                  {{ log.actor?.email ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>
