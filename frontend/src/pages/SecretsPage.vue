<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { Secret } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const { t, locale } = useI18n()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return [] as Secret[]
  }
  const { data: response } = await api.get<{ data: Secret[] }>(
    tenant.tenantPath('/secrets'),
  )
  return response.data ?? []
})

const showForm = ref(false)
const rotatingId = ref<string | null>(null)
const submitting = ref(false)
const deletingId = ref<string | null>(null)
const formError = ref<string | null>(null)
const revealedPlaintext = ref<string | null>(null)
const revealedName = ref<string | null>(null)
const copied = ref(false)

const form = reactive({
  name: '',
  value: '',
})

const formTitle = computed(() =>
  rotatingId.value ? t('secrets.rotateTitle') : t('secrets.createTitle'),
)

function formatDate(value?: string | null): string {
  if (!value) {
    return '—'
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

function openCreate(): void {
  rotatingId.value = null
  form.name = ''
  form.value = ''
  formError.value = null
  showForm.value = true
}

function openRotate(secret: Secret): void {
  rotatingId.value = secret.id
  form.name = secret.name
  form.value = ''
  formError.value = null
  showForm.value = true
}

function cancelForm(): void {
  showForm.value = false
  rotatingId.value = null
  formError.value = null
}

async function onSubmit(): Promise<void> {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return
  }

  if (rotatingId.value && !confirm(t('secrets.rotateConfirm'))) {
    return
  }

  submitting.value = true
  formError.value = null

  try {
    if (rotatingId.value) {
      const { data: response } = await api.post<{ data: Secret }>(
        tenant.tenantPath(`/secrets/${rotatingId.value}/rotate`),
        { value: form.value },
      )
      revealedPlaintext.value = response.data.plaintext ?? form.value
      revealedName.value = response.data.name
    } else {
      const { data: response } = await api.post<{ data: Secret }>(
        tenant.tenantPath('/secrets'),
        { name: form.name.trim(), value: form.value },
      )
      revealedPlaintext.value = response.data.plaintext ?? form.value
      revealedName.value = response.data.name
    }
    copied.value = false
    cancelForm()
    await reload()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('secrets.saveError')
  } finally {
    submitting.value = false
  }
}

async function onDelete(secret: Secret): Promise<void> {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return
  }
  if (!confirm(t('secrets.deleteConfirm'))) {
    return
  }

  deletingId.value = secret.id
  formError.value = null
  try {
    await api.delete(tenant.tenantPath(`/secrets/${secret.id}`))
    if (rotatingId.value === secret.id) {
      cancelForm()
    }
    await reload()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('secrets.deleteError')
  } finally {
    deletingId.value = null
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
  revealedName.value = null
  copied.value = false
}
</script>

<template>
  <div>
    <div class="mb-8 flex items-start justify-between gap-4">
      <PageHeader
        :title="$t('secrets.title')"
        :subtitle="$t('secrets.subtitle')"
      />
      <button
        type="button"
        class="shrink-0 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
        @click="openCreate"
      >
        {{ $t('secrets.create') }}
      </button>
    </div>

    <div
      v-if="revealedPlaintext"
      class="mb-6 space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950"
    >
      <h2 class="text-lg font-semibold text-amber-950 dark:text-amber-100">
        {{ $t('secrets.plaintextTitle') }}
      </h2>
      <p class="text-sm text-amber-900 dark:text-amber-200">
        {{ $t('secrets.plaintextWarning') }}
      </p>
      <p v-if="revealedName" class="text-sm font-medium text-amber-950 dark:text-amber-100">
        {{ revealedName }}
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
              ? $t('secrets.plaintextCopied')
              : $t('secrets.plaintextCopy')
          }}
        </button>
      </div>
      <button
        type="button"
        class="text-sm text-amber-900 underline hover:no-underline dark:text-amber-100"
        @click="dismissPlaintext"
      >
        {{ $t('secrets.plaintextDismiss') }}
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

      <label v-if="!rotatingId" class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">{{
          $t('secrets.fields.name')
        }}</span>
        <input
          v-model="form.name"
          required
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
        />
      </label>
      <div v-else>
        <span class="mb-1 block text-sm font-medium text-gray-700">{{
          $t('secrets.fields.name')
        }}</span>
        <p class="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 dark:border-gray-700">
          {{ form.name }}
        </p>
        <p class="mt-2 text-xs text-gray-500">
          {{ $t('secrets.rotateHint') }}
        </p>
      </div>

      <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">{{
          rotatingId
            ? $t('secrets.fields.newValue')
            : $t('secrets.fields.value')
        }}</span>
        <textarea
          v-model="form.value"
          required
          rows="3"
          class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
        />
        <span class="mt-1 block text-xs text-gray-500">{{
          $t('secrets.fields.valueHint')
        }}</span>
      </label>

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
          {{ submitting ? $t('common.loading') : $t('secrets.save') }}
        </button>
      </div>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('secrets.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('secrets.needsTenant') }}
    </div>
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      <p>{{ $t('secrets.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('secrets.emptyHint') }}</p>
      <button
        type="button"
        class="mt-4 text-sm text-violet-600 hover:underline"
        @click="openCreate"
      >
        {{ $t('secrets.create') }}
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
              {{ $t('secrets.fields.name') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('secrets.fields.version') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('common.updatedAt') }}
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
          <tr v-for="secret in data" :key="secret.id">
            <td class="px-4 py-3 font-medium">{{ secret.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">v{{ secret.version }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">
              {{ formatDate(secret.updated_at) }}
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <button
                type="button"
                class="text-violet-600 hover:underline"
                @click="openRotate(secret)"
              >
                {{ $t('secrets.rotate') }}
              </button>
              <button
                type="button"
                class="text-red-600 hover:underline disabled:opacity-60"
                :disabled="deletingId === secret.id"
                @click="onDelete(secret)"
              >
                {{ $t('common.delete') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
