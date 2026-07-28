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
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { Environment, EnvironmentPayload } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { semanticIcons } from '@/utils/icons'

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
      <BaseButton class="shrink-0" @click="openCreate">
        {{ $t('environments.create') }}
      </BaseButton>
    </div>

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
          <FormField id="env_name" :label="$t('environments.fields.name')" required>
            <template #default="{ describedBy, ariaInvalid }">
              <BaseInput
                id="env_name"
                v-model="form.name"
                required
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              />
            </template>
          </FormField>
          <FormField
            id="env_slug"
            :label="$t('environments.fields.slug')"
            :hint="$t('environments.fields.slugHint')"
          >
            <template #default="{ describedBy, ariaInvalid }">
              <BaseInput
                id="env_slug"
                v-model="form.slug"
                class="font-mono text-sm"
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              />
            </template>
          </FormField>
        </div>
        <div class="flex justify-end gap-3">
          <BaseButton variant="secondary" @click="cancelForm">
            {{ $t('common.cancel') }}
          </BaseButton>
          <BaseButton type="submit" :disabled="submitting">
            {{ submitting ? $t('common.loading') : $t('environments.save') }}
          </BaseButton>
        </div>
      </BaseCard>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('environments.loadError')"
      @retry="reload"
    />
    <EmptyState v-else-if="!tenant.currentTenantId" :message="$t('environments.needsTenant')" />
    <EmptyState v-else-if="!data?.length" :message="$t('environments.empty')">
      <p>{{ $t('environments.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('environments.emptyHint') }}</p>
      <BaseButton variant="tertiary" class="mt-4" @click="openCreate">
        {{ $t('environments.create') }}
      </BaseButton>
    </EmptyState>
    <div v-else class="overflow-hidden rounded-lg border border-border">
      <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('environments.fields.name') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('environments.fields.slug') }}
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
          <tr v-for="env in data" :key="env.id">
            <td class="px-4 py-3 font-medium text-text">{{ env.name }}</td>
            <td class="px-4 py-3 font-mono text-sm text-muted">
              {{ env.slug }}
            </td>
            <td class="px-4 py-3 text-sm">
              <BaseBadge
                :label="env.archived_at ? $t('environments.status.archived') : $t('environments.status.active')"
                :tone="env.archived_at ? 'neutral' : 'success'"
                :icon="env.archived_at ? semanticIcons.archived : semanticIcons.success"
              />
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <template v-if="!env.archived_at">
                <button type="button" class="link text-action-text" @click="openEdit(env)">
                  {{ $t('common.edit') }}
                </button>
                <button
                  type="button"
                  class="link text-danger disabled:opacity-60"
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
