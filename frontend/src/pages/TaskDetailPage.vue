<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { Task, TaskRun } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { formatScheduleHuman } from '@/utils/scheduleHuman'
import { toneForRunState, toneForTaskStatus } from '@/utils/statusTone'

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
      <RouterLink to="/tasks" class="link text-sm text-action-text">
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
          class="rounded-md border border-border px-4 py-2 text-sm text-text hover:border-border-strong"
        >
          {{ $t('common.edit') }}
        </RouterLink>
      </div>

      <BaseAlert v-if="actionError" tone="danger" class="mb-4">{{ actionError }}</BaseAlert>

      <dl class="grid gap-4 rounded-lg border border-border bg-surface p-6 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-muted">{{ $t('common.status') }}</dt>
          <dd class="mt-1 text-sm">
            <BaseBadge
              :label="$t(`tasks.status.${data.definition_status}`)"
              :tone="toneForTaskStatus(data.definition_status).tone"
              :icon="toneForTaskStatus(data.definition_status).icon"
            />
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.workspaceId') }}</dt>
          <dd class="mt-1 font-mono text-sm text-text">{{ data.workspace_id || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.taskType') }}</dt>
          <dd class="mt-1 font-mono text-sm text-text">{{ data.task_type || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.priority') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ data.priority ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.egressProfile') }}</dt>
          <dd class="mt-1 font-mono text-sm text-text">{{ data.egress_profile || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('common.description') }}</dt>
          <dd class="mt-1 text-sm text-text">{{ data.description || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.detail.schedule') }}</dt>
          <dd class="mt-1 text-sm text-text">
            {{ scheduleLabel }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.timezone') }}</dt>
          <dd class="mt-1 text-sm text-text">{{ data.timezone || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.detail.nextRun') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.next_run_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.detail.lastRun') }}</dt>
          <dd class="mt-1 flex flex-wrap items-center gap-2 text-sm tabular-nums text-text">
            {{ formatDate(data.last_run_at) }}
            <BaseBadge
              v-if="data.last_run_state"
              :label="$t(`runs.status.${data.last_run_state}`, data.last_run_state)"
              :tone="toneForRunState(data.last_run_state).tone"
              :icon="toneForRunState(data.last_run_state).icon"
            />
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.method') }}</dt>
          <dd class="mt-1 font-mono text-sm text-text">{{ data.method }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.url') }}</dt>
          <dd class="mt-1 break-all font-mono text-sm text-text">{{ data.url_or_path || '—' }}</dd>
        </div>
        <div v-if="data.query && Object.keys(data.query).length">
          <dt class="text-sm text-muted">{{ $t('tasks.fields.query') }}</dt>
          <dd class="mt-1">
            <ul class="space-y-1 font-mono text-sm text-text">
              <li v-for="(value, key) in data.query" :key="key">
                {{ key }}={{ value }}
              </li>
            </ul>
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('common.createdAt') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.created_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('common.updatedAt') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.updated_at) }}</dd>
        </div>
      </dl>

      <div class="mt-6 flex flex-wrap gap-2">
        <BaseButton v-if="status === 'draft'" :disabled="actionLoading !== null" @click="onActivate">
          {{ $t('tasks.actions.activate') }}
        </BaseButton>
        <BaseButton v-if="status === 'active'" variant="secondary" :disabled="actionLoading !== null" @click="onPause">
          {{ $t('tasks.actions.pause') }}
        </BaseButton>
        <BaseButton v-if="status === 'paused'" :disabled="actionLoading !== null" @click="onResume">
          {{ $t('tasks.actions.resume') }}
        </BaseButton>
        <BaseButton
          v-if="status === 'active' || status === 'paused' || status === 'draft'"
          variant="secondary"
          :disabled="actionLoading !== null"
          @click="onRunNow"
        >
          {{ $t('tasks.actions.runNow') }}
        </BaseButton>
        <BaseButton v-if="status !== 'archived'" variant="secondary" :disabled="actionLoading !== null" @click="onTest">
          {{ $t('tasks.actions.test') }}
        </BaseButton>
        <BaseButton variant="secondary" :disabled="actionLoading !== null" @click="onDuplicate">
          {{ $t('tasks.actions.duplicate') }}
        </BaseButton>
        <BaseButton
          v-if="status !== 'archived'"
          variant="danger"
          :disabled="actionLoading !== null"
          @click="onArchive"
        >
          {{ $t('tasks.actions.archive') }}
        </BaseButton>
        <RouterLink
          :to="{ name: 'runs', query: { task_id: id } }"
          class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm text-text hover:border-border-strong"
        >
          {{ $t('tasks.actions.viewRuns') }}
        </RouterLink>
      </div>
    </template>
  </div>
</template>
