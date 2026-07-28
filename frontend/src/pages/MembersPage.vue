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
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { Member, MemberPayload, TenantRole } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { semanticIcons } from '@/utils/icons'

const { t } = useI18n()
const tenant = useTenantStore()

const ROLE_OPTIONS: TenantRole[] = [
  'tenant_admin',
  'tenant_member',
  'read_only_viewer',
]

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId) {
    return [] as Member[]
  }
  const { data: response } = await api.get<{ data: Member[] }>(
    `/tenants/${tenant.currentTenantId}/members`,
  )
  return response.data ?? []
})

const showForm = ref(false)
const editingId = ref<string | null>(null)
const submitting = ref(false)
const removingId = ref<string | null>(null)
const formError = ref<string | null>(null)

const form = reactive({
  name: '',
  email: '',
  role: 'tenant_member' as TenantRole,
})

const formTitle = computed(() =>
  editingId.value
    ? t('settings.members.editTitle')
    : t('settings.members.inviteTitle'),
)

function roleLabel(role: string): string {
  const key = `settings.members.roles.${role}`
  const label = t(key)
  return label === key ? role : label
}

function roleTone(role: string): 'info' | 'success' | 'neutral' {
  if (role === 'tenant_admin') {
    return 'info'
  }
  if (role === 'read_only_viewer') {
    return 'neutral'
  }
  return 'success'
}

function roleIcon(role: string) {
  if (role === 'tenant_admin') {
    return semanticIcons.info
  }
  if (role === 'read_only_viewer') {
    return semanticIcons.neutral
  }
  return semanticIcons.success
}

function openInvite(): void {
  editingId.value = null
  form.name = ''
  form.email = ''
  form.role = 'tenant_member'
  formError.value = null
  showForm.value = true
}

function openEdit(member: Member): void {
  editingId.value = member.id
  form.name = member.name
  form.email = member.email
  form.role = (member.role as TenantRole) || 'tenant_member'
  formError.value = null
  showForm.value = true
}

function cancelForm(): void {
  showForm.value = false
  editingId.value = null
  formError.value = null
}

function buildPayload(): MemberPayload {
  if (editingId.value) {
    return { role: form.role }
  }
  return {
    email: form.email.trim(),
    name: form.name.trim() || undefined,
    role: form.role,
  }
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
        `/tenants/${tenant.currentTenantId}/members/${editingId.value}`,
        payload,
      )
    } else {
      await api.post(
        `/tenants/${tenant.currentTenantId}/members`,
        payload,
      )
    }
    cancelForm()
    await reload()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('settings.members.saveError')
  } finally {
    submitting.value = false
  }
}

async function onRemove(member: Member): Promise<void> {
  if (!tenant.currentTenantId) {
    return
  }
  if (!confirm(t('settings.members.removeConfirm'))) {
    return
  }
  removingId.value = member.id
  formError.value = null
  try {
    await api.delete(
      `/tenants/${tenant.currentTenantId}/members/${member.id}`,
    )
    if (editingId.value === member.id) {
      cancelForm()
    }
    await reload()
  } catch (err) {
    formError.value =
      err instanceof ApiError ? err.message : t('settings.members.removeError')
  } finally {
    removingId.value = null
  }
}
</script>

<template>
  <div>
    <div class="mb-8 flex items-start justify-between gap-4">
      <PageHeader
        :title="$t('settings.members.title')"
        :subtitle="$t('settings.members.subtitle')"
      />
      <BaseButton class="shrink-0" @click="openInvite">
        {{ $t('settings.members.invite') }}
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
          <FormField
            v-if="!editingId"
            id="member_email"
            :label="$t('settings.members.fields.email')"
            required
          >
            <template #default="{ describedBy, ariaInvalid }">
              <BaseInput
                id="member_email"
                v-model="form.email"
                type="email"
                required
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              />
            </template>
          </FormField>
          <FormField
            v-if="!editingId"
            id="member_name"
            :label="$t('settings.members.fields.name')"
            :hint="$t('settings.members.fields.nameHint')"
          >
            <template #default="{ describedBy, ariaInvalid }">
              <BaseInput
                id="member_name"
                v-model="form.name"
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              />
            </template>
          </FormField>
          <div v-if="editingId" class="sm:col-span-2">
            <p class="font-medium text-text">
              {{ form.name }}
            </p>
            <p class="text-sm text-muted">{{ form.email }}</p>
          </div>
          <FormField id="member_role" :label="$t('settings.members.fields.role')" required>
            <template #default="{ describedBy, ariaInvalid }">
              <BaseSelect
                id="member_role"
                v-model="form.role"
                required
                :described-by="describedBy"
                :aria-invalid="ariaInvalid"
              >
                <option v-for="role in ROLE_OPTIONS" :key="role" :value="role">
                  {{ roleLabel(role) }}
                </option>
              </BaseSelect>
            </template>
          </FormField>
        </div>
        <div class="flex justify-end gap-3">
          <BaseButton variant="secondary" @click="cancelForm">
            {{ $t('common.cancel') }}
          </BaseButton>
          <BaseButton type="submit" :disabled="submitting">
            {{ submitting ? $t('common.loading') : $t('settings.members.save') }}
          </BaseButton>
        </div>
      </BaseCard>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.members.loadError')"
      @retry="reload"
    />
    <EmptyState v-else-if="!tenant.currentTenantId" :message="$t('settings.members.needsTenant')" />
    <EmptyState v-else-if="!data?.length" :message="$t('settings.members.empty')">
      <p>{{ $t('settings.members.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('settings.members.emptyHint') }}</p>
      <BaseButton variant="tertiary" class="mt-4" @click="openInvite">
        {{ $t('settings.members.invite') }}
      </BaseButton>
    </EmptyState>
    <div v-else class="overflow-hidden rounded-lg border border-border">
      <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.members.fields.name') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.members.fields.email') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.members.fields.role') }}
            </th>
            <th class="px-4 py-3 text-right text-sm font-medium text-muted">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border bg-surface">
          <tr v-for="member in data" :key="member.id">
            <td class="px-4 py-3 font-medium text-text">{{ member.name }}</td>
            <td class="px-4 py-3 text-sm text-muted">{{ member.email }}</td>
            <td class="px-4 py-3 text-sm">
              <BaseBadge
                :label="roleLabel(member.role)"
                :tone="roleTone(member.role)"
                :icon="roleIcon(member.role)"
              />
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <button type="button" class="link text-action" @click="openEdit(member)">
                {{ $t('common.edit') }}
              </button>
              <button
                type="button"
                class="link text-danger disabled:opacity-60"
                :disabled="removingId === member.id"
                @click="onRemove(member)"
              >
                {{ $t('settings.members.remove') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
