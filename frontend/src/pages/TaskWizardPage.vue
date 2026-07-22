<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'

import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type {
  EndpointProfile,
  HttpMethod,
  ScheduleConfig,
  ScheduleKind,
  Task,
} from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import {
  formatSuccessStatusRanges,
  parseSuccessStatusRanges,
} from '@/utils/successStatusRanges'

const props = defineProps<{ id?: string }>()
const { t } = useI18n()
const router = useRouter()
const tenant = useTenantStore()

const step = ref(0)
const loading = ref(Boolean(props.id))
const submitting = ref(false)
const error = ref<string | null>(null)
const profiles = ref<EndpointProfile[]>([])
const scheduleHuman = ref<string | null>(null)

const SCHEDULE_KINDS: ScheduleKind[] = [
  'once',
  'every_n_minutes',
  'hourly_at',
  'daily_at',
  'weekly_on',
  'monthly_on_day',
  'business_days_at',
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
  schedule_kind: 'daily_at' as ScheduleKind,
  timezone: 'America/Sao_Paulo',
  time: '09:00',
  at: '',
  interval_minutes: 15,
  minute: 0,
  weekdays: [1, 2, 3, 4, 5] as number[],
  day: 1,
  max_attempts: 6,
  retry_strategy: 'standard_exponential',
  success_status_ranges: '',
})

const isEdit = computed(() => Boolean(props.id))

const steps = computed(() => [
  t('tasks.wizard.steps.basics'),
  t('tasks.wizard.steps.destination'),
  t('tasks.wizard.steps.schedule'),
  t('tasks.wizard.steps.review'),
])

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
    default:
      return kind
  }
})

const reviewSchedule = computed(
  () => scheduleHuman.value || localScheduleSummary.value,
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
  form.timezone = task.timezone ?? task.schedule?.timezone ?? form.timezone
  form.max_attempts = task.retry_policy?.max_attempts ?? 6
  form.retry_strategy =
    task.retry_policy?.strategy ?? 'standard_exponential'
  form.success_status_ranges = formatSuccessStatusRanges(
    task.retry_policy?.success_status_ranges,
  )
  scheduleHuman.value = task.schedule_human ?? null

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

async function onSubmit(activate: boolean): Promise<void> {
  submitting.value = true
  error.value = null

  try {
    let successRanges: Array<[number, number]> | null = null
    try {
      successRanges = parseSuccessStatusRanges(form.success_status_ranges)
    } catch {
      error.value = t('tasks.fields.successStatusRangesInvalid')
      return
    }

    const payload = {
      name: form.name,
      description: form.description || null,
      method: form.method,
      url_or_path: form.url_or_path || undefined,
      endpoint_profile_id: form.endpoint_profile_id || null,
      content_type: form.content_type || null,
      body: form.body || null,
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

    if (isEdit.value) {
      await api.patch(tenant.tenantPath(`/tasks/${props.id}`), payload)
      if (activate) {
        await api.post(tenant.tenantPath(`/tasks/${props.id}/activate`))
      }
      await router.push(`/tasks/${props.id}`)
      return
    }

    const { data } = await api.post<{ data: { id: string } }>(
      tenant.tenantPath('/tasks'),
      { ...payload, definition_status: 'draft' },
    )
    const id = data.data.id
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
      <RouterLink to="/tasks" class="text-sm text-violet-600 hover:underline">
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
              ? 'bg-violet-600 text-white'
              : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
          "
        >
          {{ index + 1 }}. {{ label }}
        </li>
      </ol>

      <div
        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
      >
        <div v-if="step === 0" class="space-y-4">
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.name') }}
            <input
              v-model="form.name"
              type="text"
              required
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.description') }}
            <textarea
              v-model="form.description"
              rows="3"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
          </label>
        </div>

        <div v-else-if="step === 1" class="space-y-4">
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.endpointProfile') }}
            <select
              v-model="form.endpoint_profile_id"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            >
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
            </select>
            <span class="mt-1 block text-xs text-gray-500">{{
              $t('tasks.fields.endpointProfileHint')
            }}</span>
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.method') }}
            <select
              v-model="form.method"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            >
              <option>GET</option>
              <option>POST</option>
              <option>PUT</option>
              <option>PATCH</option>
              <option>DELETE</option>
            </select>
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.url') }}
            <input
              v-model="form.url_or_path"
              type="text"
              :required="!form.endpoint_profile_id"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.contentType') }}
            <input
              v-model="form.content_type"
              type="text"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
          </label>
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.body') }}
            <textarea
              v-model="form.body"
              rows="4"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-950"
            />
          </label>
        </div>

        <div v-else-if="step === 2" class="space-y-4">
          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.scheduleKind') }}
            <select
              v-model="form.schedule_kind"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            >
              <option
                v-for="kind in SCHEDULE_KINDS"
                :key="kind"
                :value="kind"
              >
                {{ $t(`tasks.scheduleKinds.${kind}`) }}
              </option>
            </select>
          </label>

          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.timezone') }}
            <input
              v-model="form.timezone"
              type="text"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
          </label>

          <label
            v-if="form.schedule_kind === 'once'"
            class="block text-sm font-medium"
          >
            {{ $t('tasks.fields.at') }}
            <input
              v-model="form.at"
              type="datetime-local"
              required
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
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
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
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
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
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
            <input
              v-model="form.time"
              type="time"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
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
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
            />
          </label>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium">
              {{ $t('tasks.fields.maxAttempts') }}
              <input
                v-model.number="form.max_attempts"
                type="number"
                min="1"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
              />
            </label>
            <label class="block text-sm font-medium">
              {{ $t('tasks.fields.retryStrategy') }}
              <input
                v-model="form.retry_strategy"
                type="text"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
              />
            </label>
          </div>

          <label class="block text-sm font-medium">
            {{ $t('tasks.fields.successStatusRanges') }}
            <input
              v-model="form.success_status_ranges"
              type="text"
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-950"
              :placeholder="$t('tasks.fields.successStatusRangesPlaceholder')"
            />
            <span class="mt-1 block text-xs text-gray-500">
              {{ $t('tasks.fields.successStatusRangesHint') }}
            </span>
          </label>
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
            {{
              profiles.find((p) => p.id === form.endpoint_profile_id)?.name ||
              form.endpoint_profile_id
            }}
          </p>
          <p>
            <strong>{{ $t('tasks.fields.schedule') }}:</strong>
            {{ reviewSchedule }}
          </p>
          <p>
            <strong>{{ $t('tasks.fields.successStatusRanges') }}:</strong>
            {{ form.success_status_ranges || $t('tasks.fields.successStatusRangesDefault') }}
          </p>
        </div>

        <p v-if="error" class="mt-4 text-sm text-red-600" role="alert">
          {{ error }}
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
          <button
            v-if="step > 0"
            type="button"
            class="rounded-md border px-4 py-2 text-sm"
            @click="back"
          >
            {{ $t('common.back') }}
          </button>
          <button
            v-if="step < steps.length - 1"
            type="button"
            class="rounded-md bg-violet-600 px-4 py-2 text-sm text-white"
            :disabled="step === 0 && !form.name"
            @click="next"
          >
            {{ $t('common.next') }}
          </button>
          <template v-else>
            <button
              type="button"
              class="rounded-md border px-4 py-2 text-sm"
              :disabled="submitting || !form.name"
              @click="onSubmit(false)"
            >
              {{ $t('tasks.wizard.saveDraft') }}
            </button>
            <button
              type="button"
              class="rounded-md bg-violet-600 px-4 py-2 text-sm text-white"
              :disabled="submitting || !form.name"
              @click="onSubmit(true)"
            >
              {{ $t('tasks.wizard.activate') }}
            </button>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>
