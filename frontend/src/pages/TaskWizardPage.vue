<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'

import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type {
  EndpointProfile,
  EndpointTestResult,
  HttpMethod,
  ScheduleConfig,
  ScheduleHuman,
  ScheduleKind,
  Task,
} from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { formatScheduleHuman } from '@/utils/scheduleHuman'
import {
  formatSuccessStatusRanges,
  parseSuccessStatusRanges,
} from '@/utils/successStatusRanges'

type KeyValueRow = { key: string; value: string }

const props = defineProps<{ id?: string }>()
const { t, locale } = useI18n()
const router = useRouter()
const tenant = useTenantStore()

const step = ref(0)
const loading = ref(Boolean(props.id))
const submitting = ref(false)
const testing = ref(false)
const error = ref<string | null>(null)
const testMessage = ref<string | null>(null)
const testError = ref<string | null>(null)
const profiles = ref<EndpointProfile[]>([])
const scheduleHuman = ref<ScheduleHuman | string | null>(null)
const previewOccurrences = ref<string[]>([])
const previewError = ref<string | null>(null)
const previewLoading = ref(false)
const draftTaskId = ref<string | null>(props.id ?? null)

const SCHEDULE_KINDS: ScheduleKind[] = [
  'once',
  'every_n_minutes',
  'hourly_at',
  'daily_at',
  'weekly_on',
  'monthly_on_day',
  'business_days_at',
  'cron',
]

const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const

const form = reactive({
  name: '',
  description: '',
  method: 'POST' as HttpMethod,
  url_or_path: '',
  endpoint_profile_id: '',
  content_type: 'application/json',
  body: '',
  headers: [] as KeyValueRow[],
  query: [] as KeyValueRow[],
  schedule_kind: 'daily_at' as ScheduleKind,
  timezone: 'America/Sao_Paulo',
  time: '09:00',
  at: '',
  interval_minutes: 15,
  minute: 0,
  weekdays: [1, 2, 3, 4, 5] as number[],
  day: 1,
  cron_expression: '0 9 * * 1-5',
  max_attempts: 6,
  retry_strategy: 'standard_exponential',
  success_status_ranges: '',
})

const isEdit = computed(() => Boolean(props.id))

const steps = computed(() => [
  t('tasks.wizard.steps.basics'),
  t('tasks.wizard.steps.destination'),
  t('tasks.wizard.steps.schedule'),
  t('tasks.wizard.steps.retry'),
  t('tasks.wizard.steps.test'),
  t('tasks.wizard.steps.review'),
])

const selectedProfile = computed(
  () =>
    profiles.value.find((p) => p.id === form.endpoint_profile_id) ?? null,
)

const securityHttp = computed(() =>
  form.url_or_path.trim().toLowerCase().startsWith('http://'),
)

const securityTlsOff = computed(
  () => selectedProfile.value?.verify_tls === false,
)

const localScheduleSummary = computed(() => {
  const kind = form.schedule_kind
  const tz = form.timezone
  switch (kind) {
    case 'once':
      return `${t('tasks.scheduleKinds.once')}: ${form.at || '—'} (${tz})`
    case 'every_n_minutes':
      return `${t('tasks.scheduleKinds.every_n_minutes')}: ${form.interval_minutes}m`
    case 'hourly_at':
      return `${t('tasks.scheduleKinds.hourly_at')}: :${String(form.minute).padStart(2, '0')}`
    case 'daily_at':
      return `${t('tasks.scheduleKinds.daily_at')}: ${form.time} (${tz})`
    case 'weekly_on':
      return `${t('tasks.scheduleKinds.weekly_on')}: ${form.weekdays.join(',') } @ ${form.time} (${tz})`
    case 'monthly_on_day':
      return `${t('tasks.scheduleKinds.monthly_on_day')}: day ${form.day} @ ${form.time} (${tz})`
    case 'business_days_at':
      return `${t('tasks.scheduleKinds.business_days_at')}: ${form.time} (${tz})`
    case 'cron':
      return `${t('tasks.scheduleKinds.cron')}: ${form.cron_expression} (${tz})`
    default:
      return kind
  }
})

const reviewSchedule = computed(
  () =>
    formatScheduleHuman(scheduleHuman.value, t) || localScheduleSummary.value,
)

function formatPreviewDate(value: string): string {
  try {
    return new Intl.DateTimeFormat(locale.value, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return value
  }
}

async function refreshSchedulePreview(): Promise<void> {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    previewOccurrences.value = []
    return
  }
  if (step.value !== 2) {
    return
  }

  previewLoading.value = true
  previewError.value = null
  try {
    const { data } = await api.post<{ data: { occurrences: string[] } }>(
      tenant.tenantPath('/schedules/preview'),
      { schedule: buildSchedule(), count: 3 },
    )
    previewOccurrences.value = data.data.occurrences ?? []
  } catch (err) {
    previewOccurrences.value = []
    previewError.value =
      err instanceof ApiError ? err.message : t('tasks.wizard.previewError')
  } finally {
    previewLoading.value = false
  }
}

watch(
  () => [
    step.value,
    form.schedule_kind,
    form.timezone,
    form.at,
    form.interval_minutes,
    form.minute,
    form.time,
    form.weekdays.join(','),
    form.day,
    form.cron_expression,
  ],
  () => {
    void refreshSchedulePreview()
  },
)

function next(): void {
  if (step.value < steps.value.length - 1) {
    step.value += 1
  }
}

function back(): void {
  if (step.value > 0) {
    step.value -= 1
  }
}

function toggleWeekday(day: number, checked: boolean): void {
  if (checked) {
    if (!form.weekdays.includes(day)) {
      form.weekdays = [...form.weekdays, day].sort((a, b) => a - b)
    }
  } else {
    form.weekdays = form.weekdays.filter((d) => d !== day)
  }
}

function addHeader(): void {
  form.headers.push({ key: '', value: '' })
}

function removeHeader(index: number): void {
  form.headers.splice(index, 1)
}

function headersToRecord(): Record<string, string> {
  const record: Record<string, string> = {}
  for (const row of form.headers) {
    const key = row.key.trim()
    if (key) {
      record[key] = row.value
    }
  }
  return record
}

function addQuery(): void {
  form.query.push({ key: '', value: '' })
}

function removeQuery(index: number): void {
  form.query.splice(index, 1)
}

function queryToRecord(): Record<string, string> {
  const record: Record<string, string> = {}
  for (const row of form.query) {
    const key = row.key.trim()
    if (key) {
      record[key] = row.value
    }
  }
  return record
}

function buildSchedule(): ScheduleConfig {
  const base: ScheduleConfig = {
    kind: form.schedule_kind,
    timezone: form.timezone,
  }

  switch (form.schedule_kind) {
    case 'once':
      return { ...base, at: form.at }
    case 'every_n_minutes':
      return { ...base, interval_minutes: Number(form.interval_minutes) }
    case 'hourly_at':
      return { ...base, minute: Number(form.minute) }
    case 'daily_at':
    case 'business_days_at':
      return { ...base, time: form.time }
    case 'weekly_on':
      return { ...base, time: form.time, weekdays: [...form.weekdays] }
    case 'monthly_on_day':
      return { ...base, time: form.time, day: Number(form.day) }
    case 'cron':
      return { ...base, cron_expression: form.cron_expression }
    default:
      return base
  }
}

function toDatetimeLocal(iso?: string | null): string {
  if (!iso) {
    return ''
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function applyTask(task: Task): void {
  form.name = task.name
  form.description = task.description ?? ''
  form.method = (task.method?.toUpperCase() as HttpMethod) || 'POST'
  form.url_or_path = task.url_or_path ?? ''
  form.endpoint_profile_id = task.endpoint_profile_id ?? ''
  form.content_type = task.content_type ?? 'application/json'
  form.body = task.body ?? ''
  form.headers = Object.entries(task.headers ?? {}).map(([key, value]) => ({
    key,
    value,
  }))
  form.query = Object.entries(task.query ?? {}).map(([key, value]) => ({
    key,
    value,
  }))
  form.timezone = task.timezone ?? task.schedule?.timezone ?? form.timezone
  form.max_attempts = task.retry_policy?.max_attempts ?? 6
  form.retry_strategy =
    task.retry_policy?.strategy ?? 'standard_exponential'
  form.success_status_ranges = formatSuccessStatusRanges(
    task.retry_policy?.success_status_ranges,
  )
  scheduleHuman.value = task.schedule_human ?? null
  draftTaskId.value = task.id

  const schedule = task.schedule
  if (schedule) {
    form.schedule_kind = schedule.kind
    form.timezone = schedule.timezone || form.timezone
    form.time = schedule.time ?? form.time
    form.at = toDatetimeLocal(schedule.at)
    form.interval_minutes = schedule.interval_minutes ?? 15
    form.minute = schedule.minute ?? 0
    form.weekdays = schedule.weekdays?.length
      ? [...schedule.weekdays]
      : [1, 2, 3, 4, 5]
    form.day = schedule.day ?? 1
    form.cron_expression = schedule.cron_expression ?? form.cron_expression
  }
}

async function loadProfiles(): Promise<void> {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    profiles.value = []
    return
  }
  const { data: response } = await api.get<{ data: EndpointProfile[] }>(
    tenant.tenantPath('/endpoint-profiles'),
  )
  profiles.value = response.data ?? []
}

async function loadTask(): Promise<void> {
  if (!props.id) {
    return
  }
  const { data: response } = await api.get<{ data: Task }>(
    tenant.tenantPath(`/tasks/${props.id}`),
  )
  applyTask(response.data)
}

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    await loadProfiles()
    if (props.id) {
      await loadTask()
    }
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : t('tasks.loadError')
  } finally {
    loading.value = false
  }
})

function buildPayload(): Record<string, unknown> | null {
  let successRanges: Array<[number, number]> | null = null
  try {
    successRanges = parseSuccessStatusRanges(form.success_status_ranges)
  } catch {
    error.value = t('tasks.fields.successStatusRangesInvalid')
    return null
  }

  return {
    name: form.name,
    description: form.description || null,
    method: form.method,
    url_or_path: form.url_or_path || undefined,
    endpoint_profile_id: form.endpoint_profile_id || null,
    content_type: form.content_type || null,
    body: form.body || null,
    headers: headersToRecord(),
    query: queryToRecord(),
    timezone: form.timezone,
    schedule: buildSchedule(),
    retry_policy: {
      max_attempts: Number(form.max_attempts),
      strategy: form.retry_strategy,
      ...(successRanges
        ? { success_status_ranges: successRanges }
        : { success_status_ranges: null }),
    },
  }
}

async function ensureDraftSaved(): Promise<string | null> {
  const payload = buildPayload()
  if (!payload) {
    return null
  }

  if (draftTaskId.value) {
    await api.patch(
      tenant.tenantPath(`/tasks/${draftTaskId.value}`),
      payload,
    )
    return draftTaskId.value
  }

  const { data } = await api.post<{ data: { id: string } }>(
    tenant.tenantPath('/tasks'),
    { ...payload, definition_status: 'draft' },
  )
  draftTaskId.value = data.data.id
  return draftTaskId.value
}

async function runProfileTest(): Promise<void> {
  if (!form.endpoint_profile_id) {
    return
  }
  testing.value = true
  testMessage.value = null
  testError.value = null
  error.value = null
  try {
    const { data } = await api.post<{ data: EndpointTestResult }>(
      tenant.tenantPath(
        `/endpoint-profiles/${form.endpoint_profile_id}/test`,
      ),
      {
        path: form.url_or_path || undefined,
        body: form.body || undefined,
      },
    )
    const result = data.data
    if (result.transport_error_code) {
      testError.value = `${t('tasks.wizard.testFailed')}: ${result.transport_error_code}`
    } else {
      const status =
        result.response_status != null ? ` (${result.response_status})` : ''
      testMessage.value = `${t('tasks.wizard.testSuccess')}${status}`
    }
  } catch (err) {
    testError.value =
      err instanceof ApiError ? err.message : t('tasks.wizard.testFailed')
  } finally {
    testing.value = false
  }
}

async function runTaskTest(): Promise<void> {
  testing.value = true
  testMessage.value = null
  testError.value = null
  error.value = null
  try {
    const id = await ensureDraftSaved()
    if (!id) {
      return
    }
    await api.post(tenant.tenantPath(`/tasks/${id}/test`))
    testMessage.value = t('tasks.wizard.testSuccess')
  } catch (err) {
    testError.value =
      err instanceof ApiError ? err.message : t('tasks.wizard.testFailed')
  } finally {
    testing.value = false
  }
}

async function saveDraftOnly(): Promise<void> {
  submitting.value = true
  error.value = null
  testMessage.value = null
  testError.value = null
  try {
    const id = await ensureDraftSaved()
    if (id) {
      testMessage.value = t('tasks.wizard.saveDraft')
    }
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : t('common.error')
  } finally {
    submitting.value = false
  }
}

async function onSubmit(activate: boolean): Promise<void> {
  submitting.value = true
  error.value = null

  try {
    const payload = buildPayload()
    if (!payload) {
      return
    }

    if (isEdit.value || draftTaskId.value) {
      const id = props.id ?? draftTaskId.value!
      await api.patch(tenant.tenantPath(`/tasks/${id}`), payload)
      if (activate) {
        await api.post(tenant.tenantPath(`/tasks/${id}/activate`))
      }
      await router.push(`/tasks/${id}`)
      return
    }

    const { data } = await api.post<{ data: { id: string } }>(
      tenant.tenantPath('/tasks'),
      { ...payload, definition_status: 'draft' },
    )
    const id = data.data.id
    draftTaskId.value = id
    if (activate) {
      await api.post(tenant.tenantPath(`/tasks/${id}/activate`))
    }
    await router.push(`/tasks/${id}`)
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : t('common.error')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink to="/tasks" class="action-link">
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <PageHeader
      :title="isEdit ? $t('tasks.wizard.editTitle') : $t('tasks.wizard.title')"
    />

    <LoadingState v-if="loading" />
    <template v-else>
      <ol class="mb-8 flex flex-wrap gap-2">
        <li
          v-for="(label, index) in steps"
          :key="label"
          class="rounded-full px-3 py-1 text-xs"
          :class="
            index === step
              ? 'bg-[var(--color-action-primary)] text-[var(--color-action-primary-content)] font-medium'
              : 'bg-surface-emphasis text-muted'
          "
        >
          {{ index + 1 }}. {{ label }}
        </li>
      </ol>

      <BaseCard>
        <div v-if="step === 0" class="space-y-4">
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.name') }}
            <BaseInput v-model="form.name" type="text" required class="mt-1" />
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.description') }}
            <BaseTextarea v-model="form.description" :rows="3" class="mt-1" />
          </label>
        </div>

        <div v-else-if="step === 1" class="space-y-4">
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.endpointProfile') }}
            <BaseSelect v-model="form.endpoint_profile_id" class="mt-1">
              <option value="">
                {{ $t('tasks.fields.endpointProfileNone') }}
              </option>
              <option
                v-for="profile in profiles"
                :key="profile.id"
                :value="profile.id"
              >
                {{ profile.name }}
              </option>
            </BaseSelect>
            <span class="mt-1 block text-xs text-muted">{{
              $t('tasks.fields.endpointProfileHint')
            }}</span>
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.method') }}
            <BaseSelect v-model="form.method" class="mt-1">
              <option>GET</option>
              <option>POST</option>
              <option>PUT</option>
              <option>PATCH</option>
              <option>DELETE</option>
            </BaseSelect>
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.url') }}
            <BaseInput
              v-model="form.url_or_path"
              type="text"
              :required="!form.endpoint_profile_id"
              class="mt-1"
            />
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.contentType') }}
            <BaseInput v-model="form.content_type" type="text" class="mt-1" />
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.body') }}
            <BaseTextarea v-model="form.body" :rows="4" class="mt-1 font-mono text-sm" />
          </label>

          <fieldset>
            <legend class="mb-2 text-sm font-medium">
              {{ $t('tasks.fields.headers') }}
            </legend>
            <div class="space-y-2">
              <div
                v-for="(row, index) in form.headers"
                :key="index"
                class="key-value-row"
              >
                <div class="min-w-0">
                  <BaseInput v-model="row.key" type="text" class="font-mono text-sm" placeholder="Header" />
                </div>
                <div class="min-w-0">
                  <BaseInput v-model="row.value" type="text" class="font-mono text-sm" placeholder="Value" />
                </div>
                <BaseButton class="w-full sm:w-auto" variant="secondary" size="sm" @click="removeHeader(index)">
                  {{ $t('common.delete') }}
                </BaseButton>
              </div>
              <BaseButton variant="tertiary" size="sm" @click="addHeader">
                + {{ $t('tasks.wizard.addHeader') }}
              </BaseButton>
            </div>
          </fieldset>

          <fieldset>
            <legend class="mb-2 text-sm font-medium">
              {{ $t('tasks.fields.query') }}
            </legend>
            <div class="space-y-2">
              <div
                v-for="(row, index) in form.query"
                :key="index"
                class="key-value-row"
              >
                <div class="min-w-0">
                  <BaseInput v-model="row.key" type="text" class="font-mono text-sm" placeholder="Key" />
                </div>
                <div class="min-w-0">
                  <BaseInput v-model="row.value" type="text" class="font-mono text-sm" placeholder="Value" />
                </div>
                <BaseButton class="w-full sm:w-auto" variant="secondary" size="sm" @click="removeQuery(index)">
                  {{ $t('tasks.fields.removeQuery') }}
                </BaseButton>
              </div>
              <BaseButton variant="tertiary" size="sm" @click="addQuery">
                + {{ $t('tasks.fields.addQuery') }}
              </BaseButton>
            </div>
          </fieldset>
        </div>

        <div v-else-if="step === 2" class="space-y-4">
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.scheduleKind') }}
            <BaseSelect v-model="form.schedule_kind" class="mt-1">
              <option
                v-for="kind in SCHEDULE_KINDS"
                :key="kind"
                :value="kind"
              >
                {{ $t(`tasks.scheduleKinds.${kind}`) }}
              </option>
            </BaseSelect>
          </label>

          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.timezone') }}
            <BaseInput v-model="form.timezone" type="text" class="mt-1" />
          </label>

          <label
            v-if="form.schedule_kind === 'once'"
            class="block text-sm font-medium"
          >
            {{ $t('tasks.fields.at') }}
            <BaseInput
              v-model="form.at"
              type="datetime-local"
              required
              class="mt-1"
            />
          </label>

          <label
            v-if="form.schedule_kind === 'cron'"
            class="block text-sm font-medium"
          >
            {{ $t('tasks.fields.cronExpression') }}
            <BaseInput
              v-model="form.cron_expression"
              type="text"
              required
              placeholder="0 9 * * 1-5"
              class="mt-1 font-mono text-sm"
            />
            <span class="mt-1 block text-xs text-muted">{{
              $t('tasks.fields.cronExpressionHint')
            }}</span>
          </label>

          <label
            v-if="form.schedule_kind === 'every_n_minutes'"
            class="block text-sm font-medium"
          >
            {{ $t('tasks.fields.intervalMinutes') }}
            <input
              v-model.number="form.interval_minutes"
              type="number"
              min="1"
              class="base-control mt-1 min-h-11 w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-text"
            />
          </label>

          <label
            v-if="form.schedule_kind === 'hourly_at'"
            class="block text-sm font-medium"
          >
            {{ $t('tasks.fields.minute') }}
            <input
              v-model.number="form.minute"
              type="number"
              min="0"
              max="59"
              class="base-control mt-1 min-h-11 w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-text"
            />
          </label>

          <label
            v-if="
              ['daily_at', 'weekly_on', 'monthly_on_day', 'business_days_at'].includes(
                form.schedule_kind,
              )
            "
            class="block text-sm font-medium"
          >
            {{ $t('tasks.fields.time') }}
            <BaseInput v-model="form.time" type="time" class="mt-1" />
          </label>

          <fieldset
            v-if="form.schedule_kind === 'weekly_on'"
            class="text-sm font-medium"
          >
            <legend class="mb-2">{{ $t('tasks.fields.weekdays') }}</legend>
            <div class="flex flex-wrap gap-3">
              <label
                v-for="day in WEEKDAYS"
                :key="day"
                class="flex items-center gap-1 font-normal"
              >
                <input
                  type="checkbox"
                  :checked="form.weekdays.includes(day)"
                  @change="
                    toggleWeekday(
                      day,
                      ($event.target as HTMLInputElement).checked,
                    )
                  "
                />
                {{ $t(`tasks.weekdays.${day}`) }}
              </label>
            </div>
          </fieldset>

          <label
            v-if="form.schedule_kind === 'monthly_on_day'"
            class="block text-sm font-medium"
          >
            {{ $t('tasks.fields.dayOfMonth') }}
            <input
              v-model.number="form.day"
              type="number"
              min="1"
              max="31"
              class="base-control mt-1 min-h-11 w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-text"
            />
          </label>

          <div class="rounded-md border border-border bg-surface-emphasis p-3">
            <p class="text-sm font-medium">{{ $t('tasks.wizard.nextOccurrences') }}</p>
            <p v-if="previewLoading" class="mt-2 text-sm text-muted">
              {{ $t('common.loading') }}
            </p>
            <p v-else-if="previewError" class="mt-2 text-sm text-danger" role="alert">
              {{ previewError }}
            </p>
            <ul v-else-if="previewOccurrences.length" class="mt-2 space-y-1 text-sm text-text">
              <li v-for="occurrence in previewOccurrences" :key="occurrence">
                {{ formatPreviewDate(occurrence) }}
              </li>
            </ul>
            <p v-else class="mt-2 text-sm text-muted">
              {{ $t('tasks.wizard.noOccurrences') }}
            </p>
          </div>
        </div>

        <div v-else-if="step === 3" class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium">
              {{ $t('tasks.fields.maxAttempts') }}
              <input
                v-model.number="form.max_attempts"
                type="number"
                min="1"
                class="base-control mt-1 min-h-11 w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-text"
              />
            </label>
            <label class="block text-sm font-medium">
              {{ $t('tasks.fields.retryStrategy') }}
              <BaseInput v-model="form.retry_strategy" type="text" class="mt-1" />
            </label>
          </div>

          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.successStatusRanges') }}
            <BaseInput
              v-model="form.success_status_ranges"
              type="text"
              class="mt-1 font-mono text-sm"
              :placeholder="$t('tasks.fields.successStatusRangesPlaceholder')"
            />
            <span class="mt-1 block text-xs text-muted">
              {{ $t('tasks.fields.successStatusRangesHint') }}
            </span>
          </label>
        </div>

        <div v-else-if="step === 4" class="space-y-4">
          <template v-if="form.endpoint_profile_id">
            <p class="text-sm text-muted">
              {{ $t('tasks.wizard.testRun') }}
            </p>
            <BaseButton :disabled="testing" @click="runProfileTest">
              {{
                testing
                  ? $t('tasks.wizard.testing')
                  : $t('tasks.wizard.testRun')
              }}
            </BaseButton>
          </template>
          <template v-else>
            <BaseAlert tone="warning" role="status">
              {{ $t('tasks.wizard.testNeedsProfile') }}
            </BaseAlert>
            <div class="flex flex-wrap gap-3">
              <BaseButton
                variant="secondary"
                :disabled="submitting || !form.name"
                @click="saveDraftOnly"
              >
                {{ $t('tasks.wizard.saveDraft') }}
              </BaseButton>
              <BaseButton variant="secondary" @click="next">
                {{ $t('common.next') }}
              </BaseButton>
            </div>
          </template>

          <div v-if="draftTaskId || form.name" class="border-t border-border pt-4">
            <BaseButton
              variant="secondary"
              :disabled="testing || submitting || !form.name"
              @click="runTaskTest"
            >
              {{
                testing
                  ? $t('tasks.wizard.testing')
                  : $t('tasks.actions.test')
              }}
            </BaseButton>
          </div>

          <BaseAlert v-if="testMessage" tone="success" role="status">
            {{ testMessage }}
          </BaseAlert>
          <BaseAlert v-if="testError" tone="danger" role="alert">
            {{ testError }}
          </BaseAlert>
        </div>

        <div v-else class="space-y-2 text-sm">
          <p>
            <strong>{{ $t('tasks.fields.name') }}:</strong> {{ form.name }}
          </p>
          <p>
            <strong>{{ $t('tasks.fields.method') }}:</strong> {{ form.method }}
          </p>
          <p>
            <strong>{{ $t('tasks.fields.url') }}:</strong>
            {{ form.url_or_path || '—' }}
          </p>
          <p v-if="form.endpoint_profile_id">
            <strong>{{ $t('tasks.fields.endpointProfile') }}:</strong>
            {{ selectedProfile?.name || form.endpoint_profile_id }}
          </p>
          <p v-if="selectedProfile">
            <strong>{{ $t('tasks.wizard.selectedAuth') }}:</strong>
            {{
              $t(`endpointProfiles.authModes.${selectedProfile.auth_mode}`)
            }}
          </p>
          <div v-if="form.query.some((row) => row.key.trim())">
            <p class="font-medium">{{ $t('tasks.fields.query') }}:</p>
            <ul class="mt-1 list-inside list-disc font-mono text-xs">
              <li
                v-for="(row, index) in form.query.filter((r) => r.key.trim())"
                :key="index"
              >
                {{ row.key }}={{ row.value }}
              </li>
            </ul>
          </div>
          <p>
            <strong>{{ $t('tasks.fields.schedule') }}:</strong>
            {{ reviewSchedule }}
          </p>
          <p>
            <strong>{{ $t('tasks.fields.maxAttempts') }}:</strong>
            {{ form.max_attempts }}
          </p>
          <p>
            <strong>{{ $t('tasks.fields.retryStrategy') }}:</strong>
            {{ form.retry_strategy }}
          </p>
          <p>
            <strong>{{ $t('tasks.fields.successStatusRanges') }}:</strong>
            {{ form.success_status_ranges || $t('tasks.fields.successStatusRangesDefault') }}
          </p>

          <BaseAlert
            v-if="securityHttp || securityTlsOff"
            tone="warning"
            role="status"
            class="mt-3"
          >
            <p v-if="securityHttp">{{ $t('tasks.wizard.securityHttp') }}</p>
            <p v-if="securityTlsOff">{{ $t('tasks.wizard.securityTlsOff') }}</p>
          </BaseAlert>
        </div>

        <BaseAlert v-if="error" tone="danger" role="alert" class="mt-4">
          {{ error }}
        </BaseAlert>

        <div class="mt-6 flex flex-wrap gap-3">
          <BaseButton v-if="step > 0" variant="secondary" @click="back">
            {{ $t('common.back') }}
          </BaseButton>
          <BaseButton
            v-if="step < steps.length - 1"
            :disabled="step === 0 && !form.name"
            @click="next"
          >
            {{ $t('common.next') }}
          </BaseButton>
          <template v-else>
            <BaseButton
              variant="secondary"
              :disabled="submitting || !form.name"
              @click="onSubmit(false)"
            >
              {{ $t('tasks.wizard.saveDraft') }}
            </BaseButton>
            <BaseButton :disabled="submitting || !form.name" @click="onSubmit(true)">
              {{ $t('tasks.wizard.activate') }}
            </BaseButton>
          </template>
        </div>
      </BaseCard>
    </template>
  </div>
</template>
