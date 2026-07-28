<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import CodeBlock from '@/components/ui/CodeBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { ApiKey, ApiKeyPayload } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { toneForApiKeyStatus } from '@/utils/statusTone'

const PERMISSION_OPTIONS = [
  { value: '*', labelKey: 'settings.apiKeys.permissions.full' },
  {
    value: 'endpoint_profiles:read',
    labelKey: 'settings.apiKeys.permissions.endpoint_profiles_read',
  },
  {
    value: 'endpoint_profiles:write',
    labelKey: 'settings.apiKeys.permissions.endpoint_profiles_write',
  },
  {
    value: 'secrets:manage',
    labelKey: 'settings.apiKeys.permissions.secrets_manage',
  },
  {
    value: 'tasks:read',
    labelKey: 'settings.apiKeys.permissions.tasks_read',
  },
  {
    value: 'tasks:write',
    labelKey: 'settings.apiKeys.permissions.tasks_write',
  },
  {
    value: 'tasks:operate',
    labelKey: 'settings.apiKeys.permissions.tasks_operate',
  },
  {
    value: 'api_keys:manage',
    labelKey: 'settings.apiKeys.permissions.api_keys_manage',
  },
  {
    value: 'tenant:admin',
    labelKey: 'settings.apiKeys.permissions.tenant_admin',
  },
] as const

const { t, locale } = useI18n()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId) {
    return [] as ApiKey[]
  }
  const { data: response } = await api.get<{ data: ApiKey[] }>(
    `/tenants/${tenant.currentTenantId}/api-keys`,
  )
  return response.data ?? []
})

const showForm = ref(false)
const editingId = ref<string | null>(null)
const submitting = ref(false)
const revokingId = ref<string | null>(null)
const formError = ref<string | null>(null)
const revealedPlaintext = ref<string | null>(null)
const copied = ref(false)

const form = reactive({
  name: '',
  environment_id: '',
  expires_at: '',
  permissions: ['*'] as string[],
})

const formTitle = computed(() =>
  editingId.value
    ? t('settings.apiKeys.editTitle')
    : t('settings.apiKeys.createTitle'),
)

const editingKey = computed(
  () => data.value?.find((key) => key.id === editingId.value) ?? null,
)

const fullAccessSelected = computed(() => form.permissions.includes('*'))

function keyStatus(key: ApiKey): 'active' | 'expired' | 'revoked' {
  if (key.revoked_at) {
    return 'revoked'
  }
  if (key.expires_at && new Date(key.expires_at).getTime() < Date.now()) {
    return 'expired'
  }
  return 'active'
}

function formatDate(value?: string | null): string {
  if (!value) {
    return t('settings.apiKeys.fields.never')
  }
  try {
    return new Intl.DateTimeFormat(locale.value, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return value
  }
}

function environmentLabel(environmentId?: string | null): string {
  if (!environmentId) {
    return t('settings.apiKeys.fields.environmentAny')
  }
  const env = tenant.environments.find((item) => item.id === environmentId)
  return env?.name ?? environmentId
}

function permissionsLabel(permissions: string[]): string {
  return permissions
    .map((code) => {
      const key =
        code === '*'
          ? 'settings.apiKeys.permissions.full'
          : `settings.apiKeys.permissions.${code}`
      const label = t(key)
      return label === key ? code : label
    })
    .join(', ')
}

function toDatetimeLocal(value?: string | null): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function openCreate(): void {
  editingId.value = null
  form.name = ''
  form.environment_id = ''
  form.expires_at = ''
  form.permissions = ['*']
  formError.value = null
  revealedPlaintext.value = null
  copied.value = false
  showForm.value = true
}

function openEdit(key: ApiKey): void {
  if (keyStatus(key) === 'revoked') {
    return
  }
  editingId.value = key.id
  form.name = key.name
  form.environment_id = key.environment_id ?? ''
  form.expires_at = toDatetimeLocal(key.expires_at)
  form.permissions = key.permissions.includes('*')
    ? ['*']
    : [...key.permissions]
  formError.value = null
  revealedPlaintext.value = null
  copied.value = false
  showForm.value = true
}

function cancelForm(): void {
  showForm.value = false
  editingId.value = null
  formError.value = null
}

function togglePermission(value: string, checked: boolean): void {
  if (value === '*') {
    form.permissions = checked ? ['*'] : []
    return
  }

  const withoutFull = form.permissions.filter((item) => item !== '*')
  if (checked) {
    form.permissions = withoutFull.includes(value)
      ? withoutFull
      : [...withoutFull, value]
  } else {
    form.permissions = withoutFull.filter((item) => item !== value)
  }
}

function isPermissionChecked(value: string): boolean {
  if (value === '*') {
    return fullAccessSelected.value
  }
  return !fullAccessSelected.value && form.permissions.includes(value)
}

function buildPayload(): ApiKeyPayload {
  const payload: ApiKeyPayload = {
    name: form.name.trim(),
    permissions: form.permissions.includes('*')
      ? ['*']
      : [...form.permissions],
  }

  if (!editingId.value) {
    payload.environment_id = form.environment_id || null
  }

  payload.expires_at = form.expires_at
    ? new Date(form.expires_at).toISOString()
    : null

  return payload
}

async function onSubmit(): Promise<void> {
  if (!tenant.currentTenantId) {
    return
  }
  if (form.permissions.length === 0) {
    formError.value = t('settings.apiKeys.saveError')
    return
  }

  submitting.value = true
  formError.value = null

  try {
    const payload = buildPayload()
    if (editingId.value) {
      await api.patch(
        `/tenants/${tenant.currentTenantId}/api-keys/${editingId.value}`,
        {
          name: payload.name,
          permissions: payload.permissions,
          expires_at: payload.expires_at,
        },
      )
      cancelForm()
    } else {
      const { data: response } = await api.post<{ data: ApiKey }>(
        `/tenants/${tenant.currentTenantId}/api-keys`,
        payload,
      )
      revealedPlaintext.value = response.data.plaintext ?? null
      copied.value = false
      cancelForm()
      showForm.value = false
    }
    await reload()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('settings.apiKeys.saveError')
  } finally {
    submitting.value = false
  }
}

async function onRevoke(key: ApiKey): Promise<void> {
  if (!tenant.currentTenantId || keyStatus(key) === 'revoked') {
    return
  }
  if (!confirm(t('settings.apiKeys.revokeConfirm'))) {
    return
  }

  revokingId.value = key.id
  formError.value = null
  try {
    await api.delete(
      `/tenants/${tenant.currentTenantId}/api-keys/${key.id}`,
    )
    if (editingId.value === key.id) {
      cancelForm()
    }
    await reload()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('settings.apiKeys.revokeError')
  } finally {
    revokingId.value = null
  }
}

async function copyPlaintext(): Promise<void> {
  if (!revealedPlaintext.value) {
    return
  }
  try {
    await navigator.clipboard.writeText(revealedPlaintext.value)
    copied.value = true
  } catch {
    copied.value = false
  }
}

function dismissPlaintext(): void {
  revealedPlaintext.value = null
  copied.value = false
}
</script>

<template>
  <div>
    <div class="mb-8 flex items-start justify-between gap-4">
      <PageHeader
        :title="$t('settings.apiKeys.title')"
        :subtitle="$t('settings.apiKeys.subtitle')"
      />
      <BaseButton class="shrink-0" @click="openCreate">
        {{ $t('settings.apiKeys.create') }}
      </BaseButton>
    </div>

    <BaseAlert v-if="revealedPlaintext" tone="warning" role="alert" class="mb-6">
      <div class="space-y-3">
        <h2 class="font-semibold text-text">
          {{ $t('settings.apiKeys.plaintextTitle') }}
        </h2>
        <p>{{ $t('settings.apiKeys.plaintextWarning') }}</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
          <CodeBlock class="flex-1">{{ revealedPlaintext }}</CodeBlock>
          <BaseButton @click="copyPlaintext">
            {{
              copied
                ? $t('settings.apiKeys.plaintextCopied')
                : $t('settings.apiKeys.plaintextCopy')
            }}
          </BaseButton>
        </div>
        <button type="button" class="link text-sm text-muted" @click="dismissPlaintext">
          {{ $t('settings.apiKeys.plaintextDismiss') }}
        </button>
      </div>
    </BaseAlert>

    <BaseAlert v-if="formError && !showForm" tone="danger" role="alert" class="mb-4">
      {{ formError }}
    </BaseAlert>

    <form v-if="showForm" class="mb-6" @submit.prevent="onSubmit">
      <BaseCard class="space-y-4">
        <h2 class="text-lg font-semibold text-text">
          {{ formTitle }}
        </h2>
        <BaseAlert v-if="formError" tone="danger" role="alert">
          {{ formError }}
        </BaseAlert>

        <div class="grid gap-4 sm:grid-cols-2">
          <FormField id="apikey_name" class="sm:col-span-2" :label="$t('settings.apiKeys.fields.name')" required>
            <template #default="{ describedBy, ariaInvalid }">
              <BaseInput
                id="apikey_name"
                v-model="form.name"
                required
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              />
            </template>
          </FormField>

          <FormField
            v-if="!editingId"
            id="apikey_environment"
            :label="$t('settings.apiKeys.fields.environment')"
            :hint="$t('settings.apiKeys.fields.environmentHint')"
          >
            <template #default="{ describedBy, ariaInvalid }">
              <BaseSelect
                id="apikey_environment"
                v-model="form.environment_id"
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              >
                <option value="">
                  {{ $t('settings.apiKeys.fields.environmentAny') }}
                </option>
                <option
                  v-for="env in tenant.activeEnvironments"
                  :key="env.id"
                  :value="env.id"
                >
                  {{ env.name }}
                </option>
              </BaseSelect>
            </template>
          </FormField>

          <div v-else class="block">
            <span class="mb-1 block text-sm font-medium text-text">{{
              $t('settings.apiKeys.fields.environmentReadonly')
            }}</span>
            <p class="rounded-md border border-border px-3 py-2 text-sm text-muted">
              {{ environmentLabel(editingKey?.environment_id) }}
            </p>
          </div>

          <FormField
            id="apikey_expires_at"
            :label="$t('settings.apiKeys.fields.expiresAt')"
            :hint="$t('settings.apiKeys.fields.expiresAtHint')"
          >
            <template #default="{ describedBy, ariaInvalid }">
              <BaseInput
                id="apikey_expires_at"
                v-model="form.expires_at"
                type="datetime-local"
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              />
            </template>
          </FormField>
        </div>

        <fieldset>
          <legend class="mb-2 text-sm font-medium text-text">
            {{ $t('settings.apiKeys.fields.permissions') }}
          </legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <label
              v-for="option in PERMISSION_OPTIONS"
              :key="option.value"
              class="flex items-center gap-2 text-sm text-text"
            >
              <input
                type="checkbox"
                :checked="isPermissionChecked(option.value)"
                :disabled="option.value !== '*' && fullAccessSelected"
                @change="
                  togglePermission(
                    option.value,
                    ($event.target as HTMLInputElement).checked,
                  )
                "
              />
              {{ $t(option.labelKey) }}
            </label>
          </div>
        </fieldset>

        <div class="flex justify-end gap-3">
          <BaseButton variant="secondary" @click="cancelForm">
            {{ $t('common.cancel') }}
          </BaseButton>
          <BaseButton type="submit" :disabled="submitting">
            {{
              submitting ? $t('common.loading') : $t('settings.apiKeys.save')
            }}
          </BaseButton>
        </div>
      </BaseCard>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.apiKeys.loadError')"
      @retry="reload"
    />
    <EmptyState v-else-if="!tenant.currentTenantId" :message="$t('settings.apiKeys.needsTenant')" />
    <EmptyState v-else-if="!data?.length" :message="$t('settings.apiKeys.empty')">
      <p>{{ $t('settings.apiKeys.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('settings.apiKeys.emptyHint') }}</p>
      <BaseButton variant="tertiary" class="mt-4" @click="openCreate">
        {{ $t('settings.apiKeys.create') }}
      </BaseButton>
    </EmptyState>
    <div v-else class="overflow-x-auto rounded-lg border border-border">
      <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.apiKeys.fields.name') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.apiKeys.fields.prefix') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.apiKeys.fields.permissions') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.apiKeys.fields.environment') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.apiKeys.fields.lastUsedAt') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.apiKeys.fields.expiresAt') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('common.status') }}
            </th>
            <th class="px-4 py-3 text-right text-sm font-medium text-muted">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border bg-surface">
          <tr v-for="key in data" :key="key.id">
            <td class="px-4 py-3 font-medium text-text">{{ key.name }}</td>
            <td class="px-4 py-3 font-mono text-sm text-muted">
              {{ key.key_prefix }}…
            </td>
            <td class="max-w-xs truncate px-4 py-3 text-sm text-muted">
              {{ permissionsLabel(key.permissions ?? []) }}
            </td>
            <td class="px-4 py-3 text-sm text-muted">
              {{ environmentLabel(key.environment_id) }}
            </td>
            <td class="px-4 py-3 text-sm tabular-nums text-muted">
              {{ formatDate(key.last_used_at) }}
            </td>
            <td class="px-4 py-3 text-sm tabular-nums text-muted">
              {{
                key.expires_at
                  ? formatDate(key.expires_at)
                  : $t('settings.apiKeys.fields.never')
              }}
            </td>
            <td class="px-4 py-3 text-sm">
              <BaseBadge
                :label="$t(`settings.apiKeys.status.${keyStatus(key)}`)"
                :tone="toneForApiKeyStatus(keyStatus(key)).tone"
                :icon="toneForApiKeyStatus(keyStatus(key)).icon"
              />
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <template v-if="keyStatus(key) !== 'revoked'">
                <button type="button" class="link text-action-text" @click="openEdit(key)">
                  {{ $t('common.edit') }}
                </button>
                <button
                  type="button"
                  class="link text-danger disabled:opacity-60"
                  :disabled="revokingId === key.id"
                  @click="onRevoke(key)"
                >
                  {{ $t('settings.apiKeys.revoke') }}
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
