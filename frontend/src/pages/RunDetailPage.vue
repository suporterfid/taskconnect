<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { TaskRun, TaskRunAttempt } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ id: string }>()
const { t, locale } = useI18n()
const tenant = useTenantStore()

const actionError = ref<string | null>(null)
const actionLoading = ref<string | null>(null)

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return null
  }
  const [runRes, attemptsRes] = await Promise.all([
    api.get<{ data: TaskRun }>(tenant.tenantPath(`/task-runs/${props.id}`)),
    api.get<{ data: TaskRunAttempt[] }>(
      tenant.tenantPath(`/task-runs/${props.id}/attempts`),
    ),
  ])
  return {
    run: runRes.data.data,
    attempts: attemptsRes.data.data ?? [],
  }
})

const canCancel = computed(() => {
  const state = data.value?.run.run_state
  return state === 'pending' || state === 'running' || state === 'retry_wait'
})

const canRetry = computed(() => {
  const state = data.value?.run.run_state
  return state === 'dead' || state === 'retry_wait'
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

function formatJson(value: unknown): string {
  if (value == null) {
    return '—'
  }
  if (typeof value === 'string') {
    return value
  }
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

async function onCancel(): Promise<void> {
  actionLoading.value = 'cancel'
  actionError.value = null
  try {
    await api.post(tenant.tenantPath(`/task-runs/${props.id}/cancel`))
    await reload()
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('runs.actions.error')
  } finally {
    actionLoading.value = null
  }
}

async function onRetry(): Promise<void> {
  actionLoading.value = 'retry'
  actionError.value = null
  try {
    await api.post(tenant.tenantPath(`/task-runs/${props.id}/retry`))
    await reload()
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('runs.actions.error')
  } finally {
    actionLoading.value = null
  }
}
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink to="/runs" class="text-sm text-violet-600 hover:underline">
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('runs.loadError')" @retry="reload" />
    <template v-else-if="data">
      <PageHeader
        :title="data.run.id"
        :subtitle="$t('runs.detail.title')"
      />

      <p
        v-if="actionError"
        class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
        role="alert"
      >
        {{ actionError }}
      </p>

      <dl class="mb-6 grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.status') }}</dt>
          <dd class="mt-1 text-sm font-medium">
            {{ $t(`runs.status.${data.run.run_state}`, data.run.run_state) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('runs.fields.task') }}</dt>
          <dd class="mt-1 text-sm">
            <RouterLink
              v-if="data.run.task_id"
              :to="`/tasks/${data.run.task_id}`"
              class="text-violet-600 hover:underline"
            >
              {{ data.run.task_id }}
            </RouterLink>
            <span v-else>—</span>
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('runs.fields.trigger') }}</dt>
          <dd class="mt-1 text-sm">{{ data.run.trigger_type }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('runs.fields.attempts') }}</dt>
          <dd class="mt-1 text-sm">{{ data.run.attempt_count }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.createdAt') }}</dt>
          <dd class="mt-1 text-sm">{{ formatDate(data.run.created_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('runs.detail.startedAt') }}</dt>
          <dd class="mt-1 text-sm">{{ formatDate(data.run.started_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('runs.detail.finishedAt') }}</dt>
          <dd class="mt-1 text-sm">{{ formatDate(data.run.finished_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('runs.detail.httpStatus') }}</dt>
          <dd class="mt-1 text-sm">
            {{ data.run.final_http_status ?? '—' }}
            <span v-if="data.run.final_error_code" class="text-gray-500">
              ({{ data.run.final_error_code }})
            </span>
          </dd>
        </div>
      </dl>

      <div class="mb-8 flex flex-wrap gap-2">
        <button
          v-if="canCancel"
          type="button"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onCancel"
        >
          {{ $t('runs.actions.cancel') }}
        </button>
        <button
          v-if="canRetry"
          type="button"
          class="rounded-md bg-violet-600 px-3 py-2 text-sm text-white hover:bg-violet-700 disabled:opacity-60"
          :disabled="actionLoading !== null"
          @click="onRetry"
        >
          {{ $t('runs.actions.retry') }}
        </button>
      </div>

      <h2 class="mb-3 text-lg font-medium">{{ $t('runs.detail.attempts') }}</h2>
      <div
        v-if="!data.attempts.length"
        class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500"
      >
        {{ $t('runs.detail.noAttempts') }}
      </div>
      <ol v-else class="space-y-4">
        <li
          v-for="attempt in data.attempts"
          :key="attempt.id"
          class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        >
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="font-medium">
              {{ $t('runs.detail.attemptNumber', { n: attempt.attempt_number }) }}
              ·
              {{
                $t(
                  `runs.attemptStatus.${attempt.attempt_state}`,
                  attempt.attempt_state,
                )
              }}
            </p>
            <p class="text-sm text-gray-500">
              {{ formatDate(attempt.started_at) }}
              <span v-if="attempt.duration_ms != null">
                · {{ attempt.duration_ms }}ms
              </span>
            </p>
          </div>

          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div class="sm:col-span-2">
              <dt class="text-gray-500">{{ $t('runs.detail.requestUrl') }}</dt>
              <dd class="mt-1 break-all font-mono text-xs">
                {{ attempt.request_url_redacted || '—' }}
              </dd>
            </div>
            <div>
              <dt class="text-gray-500">{{ $t('runs.detail.responseStatus') }}</dt>
              <dd class="mt-1">{{ attempt.response_status ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">{{ $t('runs.detail.transportError') }}</dt>
              <dd class="mt-1">
                {{
                  attempt.transport_error_code ||
                  attempt.transport_error_message ||
                  '—'
                }}
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-gray-500">{{ $t('runs.detail.requestHeaders') }}</dt>
              <dd class="mt-1">
                <pre class="overflow-x-auto rounded bg-gray-50 p-2 font-mono text-xs dark:bg-gray-950">{{
                  formatJson(attempt.request_headers_redacted)
                }}</pre>
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-gray-500">{{ $t('runs.detail.requestBody') }}</dt>
              <dd class="mt-1">
                <pre class="overflow-x-auto rounded bg-gray-50 p-2 font-mono text-xs dark:bg-gray-950">{{
                  attempt.request_body_redacted || '—'
                }}</pre>
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-gray-500">{{ $t('runs.detail.responseBody') }}</dt>
              <dd class="mt-1">
                <pre class="overflow-x-auto rounded bg-gray-50 p-2 font-mono text-xs dark:bg-gray-950">{{
                  attempt.response_body_truncated || '—'
                }}</pre>
              </dd>
            </div>
          </dl>
        </li>
      </ol>
    </template>
  </div>
</template>
