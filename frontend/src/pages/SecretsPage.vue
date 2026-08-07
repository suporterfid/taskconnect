<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import CodeBlock from '@/components/ui/CodeBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
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
  const usage = secret.usage_count ?? 0
  const confirmed = confirm(
    usage > 0
      ? t('secrets.deleteConfirmInUse', { count: usage })
      : t('secrets.deleteConfirm'),
  )
  if (!confirmed) {
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
    <div class="page-header">
      <PageHeader
        :title="$t('secrets.title')"
        :subtitle="$t('secrets.subtitle')"
      />
      <BaseButton class="shrink-0" @click="openCreate">
        {{ $t('secrets.create') }}
      </BaseButton>
    </div>

    <BaseAlert v-if="revealedPlaintext" tone="warning" role="alert" class="mb-6 space-y-3">
      <h2 class="text-lg font-semibold">
        {{ $t('secrets.plaintextTitle') }}
      </h2>
      <p class="text-sm">
        {{ $t('secrets.plaintextWarning') }}
      </p>
      <p v-if="revealedName" class="text-sm font-medium">
        {{ revealedName }}
      </p>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <CodeBlock class="flex-1">{{ revealedPlaintext }}</CodeBlock>
        <BaseButton @click="copyPlaintext">
          {{
            copied
              ? $t('secrets.plaintextCopied')
              : $t('secrets.plaintextCopy')
          }}
        </BaseButton>
      </div>
      <button type="button" class="link text-sm" @click="dismissPlaintext">
        {{ $t('secrets.plaintextDismiss') }}
      </button>
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

        <FormField v-if="!rotatingId" id="secret_name" :label="$t('secrets.fields.name')" required>
          <template #default="{ describedBy, ariaInvalid }">
            <BaseInput
              id="secret_name"
              v-model="form.name"
              required
              :described-by="describedBy"
              :aria-invalid="ariaInvalid"
            />
          </template>
        </FormField>
        <div v-else>
          <span class="mb-1 block text-sm font-medium text-text">{{
            $t('secrets.fields.name')
          }}</span>
          <p class="rounded-md border border-border px-3 py-2 text-sm text-muted">
            {{ form.name }}
          </p>
          <p class="mt-2 text-xs text-muted">
            {{ $t('secrets.rotateHint') }}
          </p>
        </div>

        <FormField
          id="secret_value"
          :label="rotatingId ? $t('secrets.fields.newValue') : $t('secrets.fields.value')"
          :hint="$t('secrets.fields.valueHint')"
          required
        >
          <template #default="{ describedBy, ariaInvalid }">
            <BaseTextarea
              id="secret_value"
              v-model="form.value"
              required
              :rows="3"
              class="font-mono text-sm"
              :described-by="describedBy"
              :aria-invalid="ariaInvalid"
            />
          </template>
        </FormField>

        <div class="flex justify-end gap-3">
          <BaseButton variant="secondary" @click="cancelForm">
            {{ $t('common.cancel') }}
          </BaseButton>
          <BaseButton type="submit" :disabled="submitting">
            {{ submitting ? $t('common.loading') : $t('secrets.save') }}
          </BaseButton>
        </div>
      </BaseCard>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('secrets.loadError')"
      @retry="reload"
    />
    <EmptyState
      v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId"
      :message="$t('secrets.needsTenant')"
    />
    <EmptyState v-else-if="!data?.length" :message="$t('secrets.empty')">
      <p>{{ $t('secrets.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('secrets.emptyHint') }}</p>
      <BaseButton variant="tertiary" class="mt-4" @click="openCreate">
        {{ $t('secrets.create') }}
      </BaseButton>
    </EmptyState>
    <div v-else class="table-scroll" role="region" tabindex="0" :aria-label="$t('common.table.scrollRegion')">
      <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface">
          <tr>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('secrets.fields.name') }}
            </th>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('secrets.fields.version') }}
            </th>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('secrets.fields.usage') }}
            </th>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('common.updatedAt') }}
            </th>
            <th class="px-4 py-3 text-end text-sm font-medium text-muted">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border bg-surface">
          <tr v-for="secret in data" :key="secret.id">
            <td class="px-4 py-3 font-medium text-text">{{ secret.name }}</td>
            <td class="px-4 py-3 text-sm tabular-nums text-muted">v{{ secret.version }}</td>
            <td class="px-4 py-3 text-sm tabular-nums text-muted">
              {{ secret.usage_count ?? 0 }}
            </td>
            <td class="px-4 py-3 text-sm tabular-nums text-muted">
              {{ formatDate(secret.updated_at) }}
            </td>
            <td class="[&>*+*]:ms-3 px-4 py-3 text-end text-sm">
              <button type="button" class="link text-action-text" @click="openRotate(secret)">
                {{ $t('secrets.rotate') }}
              </button>
              <button
                type="button"
                class="link text-danger disabled:opacity-60"
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
