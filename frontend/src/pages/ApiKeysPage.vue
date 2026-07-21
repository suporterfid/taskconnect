<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { ApiKey, ApiKeyPayload } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

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

function statusBadgeClass(status: 'active' | 'expired' | 'revoked'): string {
  if (status === 'revoked') {
    return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'
  }
  if (status === 'expired') {
    return 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
  }
  return 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
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
  if (permissions.includes('*')) {
    return t('settings.apiKeys.permissions.full')
  }
  return permissions.join(', ')
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
      <button
        type="button"
        class="shrink-0 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
        @click="openCreate"
      >
        {{ $t('settings.apiKeys.create') }}
      </button>
    </div>

    <div
      v-if="revealedPlaintext"
      class="mb-6 space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950"
    >
      <h2 class="text-lg font-semibold text-amber-950 dark:text-amber-100">
        {{ $t('settings.apiKeys.plaintextTitle') }}
      </h2>
      <p class="text-sm text-amber-900 dark:text-amber-200">
        {{ $t('settings.apiKeys.plaintextWarning') }}
      </p>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <code
          class="flex-1 break-all rounded-md border border-amber-200 bg-white px-3 py-2 font-mono text-sm dark:border-amber-900 dark:bg-gray-950"
        >
          {{ revealedPlaintext }}
        </code>
        <button
          type="button"
          class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
          @click="copyPlaintext"
        >
          {{
            copied
              ? $t('settings.apiKeys.plaintextCopied')
              : $t('settings.apiKeys.plaintextCopy')
          }}
        </button>
      </div>
      <button
        type="button"
        class="text-sm text-amber-900 underline hover:no-underline dark:text-amber-100"
        @click="dismissPlaintext"
      >
        {{ $t('settings.apiKeys.plaintextDismiss') }}
      </button>
    </div>

    <p
      v-if="formError && !showForm"
      class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <form
      v-if="showForm"
      class="mb-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
      @submit.prevent="onSubmit"
    >
      <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
        {{ formTitle }}
      </h2>
      <p
        v-if="formError"
        class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {{ formError }}
      </p>

      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('settings.apiKeys.fields.name')
          }}</span>
          <input
            v-model="form.name"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>

        <label v-if="!editingId" class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('settings.apiKeys.fields.environment')
          }}</span>
          <select
            v-model="form.environment_id"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
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
          </select>
          <span class="mt-1 block text-xs text-gray-500">{{
            $t('settings.apiKeys.fields.environmentHint')
          }}</span>
        </label>

        <div v-else class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('settings.apiKeys.fields.environmentReadonly')
          }}</span>
          <p class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 dark:border-gray-700">
            {{ environmentLabel(editingKey?.environment_id) }}
          </p>
        </div>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('settings.apiKeys.fields.expiresAt')
          }}</span>
          <input
            v-model="form.expires_at"
            type="datetime-local"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
          <span class="mt-1 block text-xs text-gray-500">{{
            $t('settings.apiKeys.fields.expiresAtHint')
          }}</span>
        </label>
      </div>

      <fieldset>
        <legend class="mb-2 text-sm font-medium text-gray-700">
          {{ $t('settings.apiKeys.fields.permissions') }}
        </legend>
        <div class="grid gap-2 sm:grid-cols-2">
          <label
            v-for="option in PERMISSION_OPTIONS"
            :key="option.value"
            class="flex items-center gap-2 text-sm text-gray-700"
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
        <button
          type="button"
          class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="cancelForm"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          type="submit"
          :disabled="submitting"
          class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-60"
        >
          {{
            submitting ? $t('common.loading') : $t('settings.apiKeys.save')
          }}
        </button>
      </div>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.apiKeys.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!tenant.currentTenantId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('settings.apiKeys.needsTenant') }}
    </div>
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      <p>{{ $t('settings.apiKeys.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('settings.apiKeys.emptyHint') }}</p>
      <button
        type="button"
        class="mt-4 text-sm text-violet-600 hover:underline"
        @click="openCreate"
      >
        {{ $t('settings.apiKeys.create') }}
      </button>
    </div>
    <div
      v-else
      class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800"
    >
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.apiKeys.fields.name') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.apiKeys.fields.prefix') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.apiKeys.fields.permissions') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.apiKeys.fields.environment') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.apiKeys.fields.lastUsedAt') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.apiKeys.fields.expiresAt') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('common.status') }}
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
          <tr v-for="key in data" :key="key.id">
            <td class="px-4 py-3 font-medium">{{ key.name }}</td>
            <td class="px-4 py-3 font-mono text-sm text-gray-600">
              {{ key.key_prefix }}…
            </td>
            <td class="max-w-xs truncate px-4 py-3 text-sm text-gray-600">
              {{ permissionsLabel(key.permissions ?? []) }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              {{ environmentLabel(key.environment_id) }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              {{ formatDate(key.last_used_at) }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              {{
                key.expires_at
                  ? formatDate(key.expires_at)
                  : $t('settings.apiKeys.fields.never')
              }}
            </td>
            <td class="px-4 py-3 text-sm">
              <span
                class="rounded px-2 py-0.5 text-xs font-medium"
                :class="statusBadgeClass(keyStatus(key))"
              >
                {{ $t(`settings.apiKeys.status.${keyStatus(key)}`) }}
              </span>
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <template v-if="keyStatus(key) !== 'revoked'">
                <button
                  type="button"
                  class="text-violet-600 hover:underline"
                  @click="openEdit(key)"
                >
                  {{ $t('common.edit') }}
                </button>
                <button
                  type="button"
                  class="text-red-600 hover:underline disabled:opacity-60"
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
