<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { Member, MemberPayload, TenantRole } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

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
      <button
        type="button"
        class="shrink-0 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
        @click="openInvite"
      >
        {{ $t('settings.members.invite') }}
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
        <label v-if="!editingId" class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('settings.members.fields.email')
          }}</span>
          <input
            v-model="form.email"
            type="email"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>
        <label v-if="!editingId" class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('settings.members.fields.name')
          }}</span>
          <input
            v-model="form.name"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
          <span class="mt-1 block text-xs text-gray-500">{{
            $t('settings.members.fields.nameHint')
          }}</span>
        </label>
        <div v-if="editingId" class="sm:col-span-2">
          <p class="font-medium text-gray-900 dark:text-gray-100">
            {{ form.name }}
          </p>
          <p class="text-sm text-gray-500">{{ form.email }}</p>
        </div>
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('settings.members.fields.role')
          }}</span>
          <select
            v-model="form.role"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          >
            <option v-for="role in ROLE_OPTIONS" :key="role" :value="role">
              {{ roleLabel(role) }}
            </option>
          </select>
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
          {{ submitting ? $t('common.loading') : $t('settings.members.save') }}
        </button>
      </div>
    </form>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.members.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!tenant.currentTenantId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('settings.members.needsTenant') }}
    </div>
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      <p>{{ $t('settings.members.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('settings.members.emptyHint') }}</p>
      <button
        type="button"
        class="mt-4 text-sm text-violet-600 hover:underline"
        @click="openInvite"
      >
        {{ $t('settings.members.invite') }}
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
              {{ $t('settings.members.fields.name') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.members.fields.email') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.members.fields.role') }}
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
          <tr v-for="member in data" :key="member.id">
            <td class="px-4 py-3 font-medium">{{ member.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ member.email }}</td>
            <td class="px-4 py-3 text-sm">
              <span
                class="rounded px-2 py-0.5 text-xs font-medium"
                :class="
                  member.role === 'tenant_admin'
                    ? 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300'
                    : member.role === 'read_only_viewer'
                      ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'
                      : 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
                "
              >
                {{ roleLabel(member.role) }}
              </span>
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <button
                type="button"
                class="text-violet-600 hover:underline"
                @click="openEdit(member)"
              >
                {{ $t('common.edit') }}
              </button>
              <button
                type="button"
                class="text-red-600 hover:underline disabled:opacity-60"
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
