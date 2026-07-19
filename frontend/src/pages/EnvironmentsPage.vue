<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { Environment, EnvironmentPayload } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const { t } = useI18n()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId) {
    return [] as Environment[]
  }
  const { data: response } = await api.get<{ data: Environment[] }>(
    `/tenants/${tenant.currentTenantId}/environments`,
  )
  const list = response.data ?? []
  return [...list].sort((a, b) => {
    const aArchived = Boolean(a.archived_at)
    const bArchived = Boolean(b.archived_at)
    if (aArchived !== bArchived) {
      return aArchived ? 1 : -1
    }
    return a.name.localeCompare(b.name)
  })
})

const showForm = ref(false)
const editingId = ref<string | null>(null)
const submitting = ref(false)
const archivingId = ref<string | null>(null)
const formError = ref<string | null>(null)

const form = reactive({
  name: '',
  slug: '',
})

const formTitle = computed(() =>
  editingId.value
    ? t('environments.editTitle')
    : t('environments.createTitle'),
)

function openCreate(): void {
  editingId.value = null
  form.name = ''
  form.slug = ''
  formError.value = null
  showForm.value = true
}

function openEdit(env: Environment): void {
  editingId.value = env.id
  form.name = env.name
  form.slug = env.slug
  formError.value = null
  showForm.value = true
}

function cancelForm(): void {
  showForm.value = false
  editingId.value = null
  formError.value = null
}

function buildPayload(): EnvironmentPayload {
  const payload: EnvironmentPayload = {
    name: form.name.trim(),
  }
  const slug = form.slug.trim()
  if (slug) {
    payload.slug = slug
  }
  return payload
}

async function refreshLists(): Promise<void> {
  if (tenant.currentTenantId) {
    await tenant.fetchEnvironments(tenant.currentTenantId)
  }
  await reload()
}

async function onSubmit(): Promise<void> {
  if (!tenant.currentTenantId) {
    return
  }
  submitting.value = true
  formError.value = null

  try {
    const payload = buildPayload()
    if (editingId.value) {
      await api.patch(
        `/tenants/${tenant.currentTenantId}/environments/${editingId.value}`,
        payload,
      )
    } else {
      await api.post(
        `/tenants/${tenant.currentTenantId}/environments`,
        payload,
      )
    }
    cancelForm()
    await refreshLists()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('environments.saveError')
  } finally {
    submitting.value = false
  }
}

async function onArchive(env: Environment): Promise<void> {
  if (!tenant.currentTenantId) {
    return
  }
  if (!confirm(t('environments.archiveConfirm'))) {
    return
  }
  archivingId.value = env.id
  formError.value = null
  try {
    await api.delete(
      `/tenants/${tenant.currentTenantId}/environments/${env.id}`,
    )
    if (editingId.value === env.id) {
      cancelForm()
    }
    await refreshLists()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('environments.archiveError')
  } finally {
    archivingId.value = null
  }
}
</script>

<template>
  <div>
    <div class="mb-8 flex items-start justify-between gap-4">
      <PageHeader
        :title="$t('environments.title')"
        :subtitle="$t('environments.subtitle')"
      />
      <button
        type="button"
        class="shrink-0 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
        @click="openCreate"
      >
        {{ $t('environments.create') }}
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
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('environments.fields.name')
          }}</span>
          <input
            v-model="form.name"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('environments.fields.slug')
          }}</span>
          <input
            v-model="form.slug"
            class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
          />
          <span class="mt-1 block text-xs text-gray-500">{{
            $t('environments.fields.slugHint')
          }}</span>
        </label>
      </div>
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
          {{ submitting ? $t('common.loading') : $t('environments.save') }}
        </button>
      </div>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('environments.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!tenant.currentTenantId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('environments.needsTenant') }}
    </div>
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      <p>{{ $t('environments.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('environments.emptyHint') }}</p>
      <button
        type="button"
        class="mt-4 text-sm text-violet-600 hover:underline"
        @click="openCreate"
      >
        {{ $t('environments.create') }}
      </button>
    </div>
    <div
      v-else
      class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"
    >
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('environments.fields.name') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('environments.fields.slug') }}
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
          <tr v-for="env in data" :key="env.id">
            <td class="px-4 py-3 font-medium">{{ env.name }}</td>
            <td class="px-4 py-3 font-mono text-sm text-gray-600">
              {{ env.slug }}
            </td>
            <td class="px-4 py-3 text-sm">
              <span
                class="rounded px-2 py-0.5 text-xs font-medium"
                :class="
                  env.archived_at
                    ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'
                    : 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
                "
              >
                {{
                  env.archived_at
                    ? $t('environments.status.archived')
                    : $t('environments.status.active')
                }}
              </span>
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <template v-if="!env.archived_at">
                <button
                  type="button"
                  class="text-violet-600 hover:underline"
                  @click="openEdit(env)"
                >
                  {{ $t('common.edit') }}
                </button>
                <button
                  type="button"
                  class="text-red-600 hover:underline disabled:opacity-60"
                  :disabled="archivingId === env.id"
                  @click="onArchive(env)"
                >
                  {{ $t('environments.archive') }}
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
