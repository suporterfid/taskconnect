<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { Task, TaskRun } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { formatScheduleHuman } from '@/utils/scheduleHuman'

const props = defineProps<{ id: string }>()
const { t, locale } = useI18n()
const router = useRouter()
const tenant = useTenantStore()

const actionError = ref<string | null>(null)
const actionLoading = ref<string | null>(null)

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return null
  }
  const { data: response } = await api.get<{ data: Task }>(
    tenant.tenantPath(`/tasks/${props.id}`),
  )
  return response.data
})

const status = computed(() => data.value?.definition_status)

const scheduleLabel = computed(() => {
  const formatted = formatScheduleHuman(data.value?.schedule_human, t)
  return formatted || data.value?.schedule?.kind || '—'
})

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

async function runAction(
  key: string,
  fn: () => Promise<void>,
): Promise<void> {
  actionLoading.value = key
  actionError.value = null
  try {
    await fn()
    await reload()
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('tasks.actions.error')
  } finally {
    actionLoading.value = null
  }
}

function onActivate(): void {
  void runAction('activate', async () => {
    await api.post(tenant.tenantPath(`/tasks/${props.id}/activate`))
  })
}

function onPause(): void {
  void runAction('pause', async () => {
    await api.post(tenant.tenantPath(`/tasks/${props.id}/pause`))
  })
}

function onResume(): void {
  void runAction('resume', async () => {
    await api.post(tenant.tenantPath(`/tasks/${props.id}/resume`))
  })
}

function onRunNow(): void {
  void runAction('runNow', async () => {
    const { data: response } = await api.post<{ data: TaskRun }>(
      tenant.tenantPath(`/tasks/${props.id}/run-now`),
    )
    if (response.data?.id) {
      await router.push(`/runs/${response.data.id}`)
    }
  })
}

function onTest(): void {
  void runAction('test', async () => {
    const { data: response } = await api.post<{ data: TaskRun }>(
      tenant.tenantPath(`/tasks/${props.id}/test`),
    )
    if (response.data?.id) {
      await router.push(`/runs/${response.data.id}`)
    }
  })
}

function onDuplicate(): void {
  void runAction('duplicate', async () => {
    const { data: response } = await api.post<{ data: Task }>(
      tenant.tenantPath(`/tasks/${props.id}/duplicate`),
    )
    if (response.data?.id) {
      await router.push(`/tasks/${response.data.id}`)
    }
  })
}

async function onArchive(): Promise<void> {
  if (!confirm(t('tasks.actions.archiveConfirm'))) {
    return
  }
  await runAction('archive', async () => {
    await api.delete(tenant.tenantPath(`/tasks/${props.id}`))
    await router.push('/tasks')
  })
}
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink to="/tasks" class="text-sm text-violet-600 hover:underline">
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('tasks.loadError')" @retry="reload" />
    <template v-else-if="data">
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <PageHeader :title="data.name" :subtitle="$t('tasks.detail.title')" />
        <RouterLink
          :to="`/tasks/${id}/edit`"
          class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          {{ $t('common.edit') }}
        </RouterLink>
      </div>

      <p
        v-if="actionError"
        class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
        role="alert"
      >
        {{ actionError }}
      </p>

      <dl class="grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.status') }}</dt>
          <dd class="mt-1 text-sm font-medium">
            {{ $t(`tasks.status.${data.definition_status}`) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.description') }}</dt>
          <dd class="mt-1 text-sm">{{ data.description || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('tasks.detail.schedule') }}</dt>
          <dd class="mt-1 text-sm">
            {{ scheduleLabel }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('tasks.fields.timezone') }}</dt>
          <dd class="mt-1 text-sm">{{ data.timezone || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('tasks.detail.nextRun') }}</dt>
          <dd class="mt-1 text-sm">{{ formatDate(data.next_run_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('tasks.detail.lastRun') }}</dt>
          <dd class="mt-1 text-sm">
            {{ formatDate(data.last_run_at) }}
            <span v-if="data.last_run_state" class="text-gray-500">
              ({{ $t(`runs.status.${data.last_run_state}`, data.last_run_state) }})
            </span>
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('tasks.fields.method') }}</dt>
          <dd class="mt-1 font-mono text-sm">{{ data.method }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('tasks.fields.url') }}</dt>
          <dd class="mt-1 break-all font-mono text-sm">{{ data.url_or_path || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.createdAt') }}</dt>
          <dd class="mt-1 text-sm">{{ formatDate(data.created_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.updatedAt') }}</dt>
          <dd class="mt-1 text-sm">{{ formatDate(data.updated_at) }}</dd>
        </div>
      </dl>

      <div class="mt-6 flex flex-wrap gap-2">
        <button
          v-if="status === 'draft'"
          type="button"
          class="rounded-md bg-violet-600 px-3 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onActivate"
        >
          {{ $t('tasks.actions.activate') }}
        </button>
        <button
          v-if="status === 'active'"
          type="button"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onPause"
        >
          {{ $t('tasks.actions.pause') }}
        </button>
        <button
          v-if="status === 'paused'"
          type="button"
          class="rounded-md bg-violet-600 px-3 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onResume"
        >
          {{ $t('tasks.actions.resume') }}
        </button>
        <button
          v-if="status === 'active' || status === 'paused' || status === 'draft'"
          type="button"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onRunNow"
        >
          {{ $t('tasks.actions.runNow') }}
        </button>
        <button
          v-if="status !== 'archived'"
          type="button"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onTest"
        >
          {{ $t('tasks.actions.test') }}
        </button>
        <button
          type="button"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onDuplicate"
        >
          {{ $t('tasks.actions.duplicate') }}
        </button>
        <button
          v-if="status !== 'archived'"
          type="button"
          class="rounded-md border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onArchive"
        >
          {{ $t('tasks.actions.archive') }}
        </button>
        <RouterLink
          :to="{ name: 'runs', query: { task_id: id } }"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50"
        >
          {{ $t('tasks.actions.viewRuns') }}
        </RouterLink>
      </div>
    </template>
  </div>
</template>
