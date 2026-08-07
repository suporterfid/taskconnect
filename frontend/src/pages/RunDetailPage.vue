<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CodeBlock from '@/components/ui/CodeBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { TaskRun, TaskRunAttempt } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { maskIdempotencyKey } from '@/utils/scheduleHuman'
import { toneForRunState } from '@/utils/statusTone'

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

const terminalExplanation = computed(() => {
  const run = data.value?.run
  if (!run) {
    return null
  }
  if (run.run_state === 'dead') {
    return t('runs.detail.terminal.dead', {
      code: run.final_error_code || t('runs.detail.terminal.unknownCode'),
      status: run.final_http_status ?? '—',
    })
  }
  if (run.run_state === 'blocked') {
    return t('runs.detail.terminal.blocked', {
      code: run.final_error_code || t('runs.detail.terminal.unknownCode'),
    })
  }
  if (run.run_state === 'cancelled') {
    return t('runs.detail.terminal.cancelled')
  }
  return null
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
      <RouterLink to="/runs" class="action-link">
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

      <BaseAlert v-if="actionError" tone="danger" class="mb-4">{{ actionError }}</BaseAlert>

      <BaseAlert v-if="terminalExplanation" tone="warning" role="status" class="mb-4">
        {{ terminalExplanation }}
      </BaseAlert>

      <dl class="mb-6 grid gap-4 rounded-lg border border-border bg-surface p-6 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-muted">{{ $t('common.status') }}</dt>
          <dd class="mt-1 text-sm">
            <BaseBadge
              :label="$t(`runs.status.${data.run.run_state}`, data.run.run_state)"
              :tone="toneForRunState(data.run.run_state).tone"
              :icon="toneForRunState(data.run.run_state).icon"
            />
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.fields.task') }}</dt>
          <dd class="mt-1 text-sm">
            <RouterLink v-if="data.run.task_id" :to="`/tasks/${data.run.task_id}`" class="link text-action-text">
              {{ data.run.task_id }}
            </RouterLink>
            <span v-else class="text-muted">—</span>
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.fields.trigger') }}</dt>
          <dd class="mt-1 text-sm text-text">{{ data.run.trigger_type }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.fields.attempts') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ data.run.attempt_count }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.detail.scheduledFor') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.run.scheduled_for) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.detail.idempotencyKey') }}</dt>
          <dd class="mt-1 font-mono text-sm text-text" :title="data.run.idempotency_key ?? undefined">
            {{ maskIdempotencyKey(data.run.idempotency_key) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.detail.nextAttemptAt') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.run.next_attempt_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('common.createdAt') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.run.created_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.detail.startedAt') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.run.started_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.detail.finishedAt') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.run.finished_at) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('runs.detail.httpStatus') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">
            {{ data.run.final_http_status ?? '—' }}
            <span v-if="data.run.final_error_code" class="text-muted">
              ({{ data.run.final_error_code }})
            </span>
          </dd>
        </div>
      </dl>

      <div class="mb-8 flex flex-wrap gap-2">
        <BaseButton v-if="canCancel" variant="secondary" :disabled="actionLoading !== null" @click="onCancel">
          {{ $t('runs.actions.cancel') }}
        </BaseButton>
        <BaseButton v-if="canRetry" :disabled="actionLoading !== null" @click="onRetry">
          {{ $t('runs.actions.retry') }}
        </BaseButton>
      </div>

      <h2 class="mb-3">{{ $t('runs.detail.attempts') }}</h2>
      <EmptyState v-if="!data.attempts.length" :message="$t('runs.detail.noAttempts')" />
      <ol v-else class="space-y-4">
        <li
          v-for="attempt in data.attempts"
          :key="attempt.id"
          class="rounded-lg border border-border bg-surface p-4"
        >
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="font-medium text-text">
              {{ $t('runs.detail.attemptNumber', { n: attempt.attempt_number }) }}
              ·
              {{
                $t(
                  `runs.attemptStatus.${attempt.attempt_state}`,
                  attempt.attempt_state,
                )
              }}
            </p>
            <p class="text-sm tabular-nums text-muted">
              {{ formatDate(attempt.started_at) }}
              <span v-if="attempt.duration_ms != null">
                · {{ attempt.duration_ms }}ms
              </span>
            </p>
          </div>

          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div class="sm:col-span-2">
              <dt class="text-muted">{{ $t('runs.detail.requestUrl') }}</dt>
              <dd class="mt-1 break-all font-mono text-xs text-text">
                {{ attempt.request_url_redacted || '—' }}
              </dd>
            </div>
            <div>
              <dt class="text-muted">{{ $t('runs.detail.responseStatus') }}</dt>
              <dd class="mt-1 tabular-nums text-text">{{ attempt.response_status ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-muted">{{ $t('runs.detail.nextRetryAt') }}</dt>
              <dd class="mt-1 tabular-nums text-text">{{ formatDate(attempt.next_retry_at) }}</dd>
            </div>
            <div>
              <dt class="text-muted">{{ $t('runs.detail.transportError') }}</dt>
              <dd class="mt-1 text-text">
                {{
                  attempt.transport_error_code ||
                  attempt.transport_error_message ||
                  '—'
                }}
              </dd>
            </div>
            <div class="sm:col-span-2">
              <CodeBlock :label="$t('runs.detail.requestHeaders')">{{
                formatJson(attempt.request_headers_redacted)
              }}</CodeBlock>
            </div>
            <div class="sm:col-span-2">
              <CodeBlock :label="$t('runs.detail.requestBody')">{{
                attempt.request_body_redacted || '—'
              }}</CodeBlock>
            </div>
            <div class="sm:col-span-2">
              <CodeBlock :label="$t('runs.detail.responseBody')">{{
                attempt.response_body_truncated || '—'
              }}</CodeBlock>
            </div>
          </dl>
        </li>
      </ol>
    </template>
  </div>
</template>
