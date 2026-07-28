<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
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

async function onLocaleChange(locale: string): Promise<void> {
  localeStore.switchLocale(locale as SupportedLocale)
  await persistPreferences({ locale: locale as SupportedLocale })
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
      <BaseCard>
        <h2 class="text-lg font-medium text-text">{{ $t('settings.sections.locale') }}</h2>
        <p class="mt-2 text-sm text-muted">
          {{ $t('settings.locale.hint') }}
        </p>

        <FormField id="locale" class="mt-4 max-w-xs" :label="$t('common.locale.label')">
          <template #default="{ describedBy, ariaInvalid }">
            <BaseSelect
              id="locale"
              :model-value="localeStore.currentLocale"
              :disabled="saving"
              :described-by="describedBy"
              :aria-invalid="ariaInvalid"
              @update:model-value="onLocaleChange"
            >
              <option value="en">{{ $t('common.locale.en') }}</option>
              <option value="pt-BR">{{ $t('common.locale.pt-BR') }}</option>
            </BaseSelect>
          </template>
        </FormField>

        <FormField id="timezone" class="mt-4 max-w-xs" :label="$t('settings.timezone.label')">
          <template #default="{ describedBy, ariaInvalid }">
            <BaseInput
              id="timezone"
              v-model="timezone"
              type="text"
              :disabled="saving"
              :described-by="describedBy"
              :aria-invalid="ariaInvalid"
            />
          </template>
        </FormField>
        <BaseButton class="mt-3" :disabled="saving" @click="onTimezoneSave">
          {{ $t('settings.timezone.save') }}
        </BaseButton>

        <BaseAlert v-if="note" tone="success" role="status" class="mt-3">
          {{ note }}
        </BaseAlert>
        <BaseAlert v-if="saveError" tone="danger" role="alert" class="mt-3">
          {{ saveError }}
        </BaseAlert>
      </BaseCard>

      <BaseCard>
        <h2 class="text-lg font-medium text-text">
          {{ $t('settings.notifications.title') }}
        </h2>
        <p class="mt-2 text-sm text-muted">
          {{ $t('settings.notifications.hint') }}
        </p>

        <label class="mt-4 inline-flex items-start gap-2 text-sm text-text">
          <input
            v-model="failureEmailsEnabled"
            type="checkbox"
            class="mt-0.5 rounded"
            :disabled="saving"
          />
          <span>
            <span class="font-medium">{{ $t('settings.notifications.failureEmails') }}</span>
            <span class="mt-1 block text-muted">
              {{ $t('settings.notifications.failureEmailsHint') }}
            </span>
          </span>
        </label>
        <div>
          <BaseButton class="mt-3" :disabled="saving" @click="onFailureEmailsSave">
            {{ $t('settings.notifications.save') }}
          </BaseButton>
        </div>
      </BaseCard>

      <BaseCard>
        <h2 class="text-lg font-medium text-text">
          {{ $t('settings.outboundAllowHosts.title') }}
        </h2>
        <p class="mt-2 text-sm text-muted">
          {{ $t('settings.outboundAllowHosts.hint') }}
        </p>

        <EmptyState v-if="!canEditAllowHosts" class="mt-4" :message="$t('settings.outboundAllowHosts.needsTenant')" />
        <template v-else>
          <FormField
            id="allow_hosts"
            class="mt-4"
            :label="$t('settings.outboundAllowHosts.label')"
          >
            <template #default="{ describedBy, ariaInvalid }">
              <BaseTextarea
                id="allow_hosts"
                v-model="allowHostsText"
                :rows="5"
                class="font-mono text-sm"
                :disabled="savingAllowHosts"
                :placeholder="$t('settings.outboundAllowHosts.placeholder')"
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              />
            </template>
          </FormField>
          <BaseButton class="mt-3" :disabled="savingAllowHosts" @click="onSaveAllowHosts">
            {{ $t('settings.outboundAllowHosts.save') }}
          </BaseButton>
          <BaseAlert v-if="allowHostsNote" tone="success" role="status" class="mt-3">
            {{ allowHostsNote }}
          </BaseAlert>
          <BaseAlert v-if="allowHostsError" tone="danger" role="alert" class="mt-3">
            {{ allowHostsError }}
          </BaseAlert>
        </template>
      </BaseCard>

      <BaseCard>
        <h2 class="text-lg font-medium text-text">{{ $t('settings.retention.title') }}</h2>
        <p class="mt-2 text-sm text-muted">
          {{ $t('settings.retention.subtitle') }}
        </p>
        <p class="mt-1 text-sm text-muted">
          {{ $t('settings.retention.hint') }}
        </p>

        <LoadingState v-if="retentionLoading" />
        <ErrorState
          v-else-if="retentionError"
          :message="retentionError ?? $t('settings.retention.loadError')"
          @retry="reloadRetention"
        />
        <dl v-else-if="retention" class="mt-4 grid gap-3 sm:grid-cols-2">
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.payloadSnapshotsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retention.payload_snapshots_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.attemptMetadataDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retention.attempt_metadata_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.runSummaryDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retention.run_summary_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.auditLogsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retention.audit_logs_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.apiIdempotencyHours') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retention.api_idempotency_hours }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.systemHeartbeatDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retention.system_heartbeat_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.deadRunsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retention.dead_runs_days ?? '—' }}
            </dd>
          </div>
        </dl>
      </BaseCard>

      <BaseCard>
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-medium text-text">{{ $t('settings.audit.title') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $t('settings.audit.subtitle') }}</p>
          </div>
          <RouterLink v-if="canLoadAudit" to="/audit-logs" class="link text-sm text-action-text">
            {{ $t('settings.audit.viewAll') }}
          </RouterLink>
        </div>

        <EmptyState v-if="!canLoadAudit" :message="$t('settings.audit.needsTenant')" />
        <LoadingState v-else-if="auditLoading" />
        <ErrorState
          v-else-if="auditError"
          :message="auditError ?? $t('settings.audit.loadError')"
          @retry="reloadAudit"
        />
        <EmptyState v-else-if="!auditLogs?.length" :message="$t('settings.audit.empty')" />
        <div v-else class="overflow-hidden rounded-md border border-border">
          <table class="min-w-full divide-y divide-border">
            <thead class="bg-surface-emphasis">
              <tr>
                <th class="px-3 py-2 text-left text-sm font-medium text-muted">
                  {{ $t('settings.audit.fields.when') }}
                </th>
                <th class="px-3 py-2 text-left text-sm font-medium text-muted">
                  {{ $t('settings.audit.fields.action') }}
                </th>
                <th class="px-3 py-2 text-left text-sm font-medium text-muted">
                  {{ $t('settings.audit.fields.resource') }}
                </th>
                <th class="px-3 py-2 text-left text-sm font-medium text-muted">
                  {{ $t('settings.audit.fields.actor') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="log in auditLogs.slice(0, 8)" :key="log.id">
                <td class="px-3 py-2 text-sm tabular-nums text-text">{{ formatWhen(log.created_at) }}</td>
                <td class="px-3 py-2 text-sm font-medium text-text">{{ log.action }}</td>
                <td class="px-3 py-2 text-sm text-muted">
                  {{ log.resource_type }}
                  <span v-if="log.resource_id"> · {{ log.resource_id }}</span>
                </td>
                <td class="px-3 py-2 text-sm text-muted">
                  {{ log.actor?.email ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>
    </div>
  </div>
</template>
