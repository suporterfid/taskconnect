<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'

import PageHeader from '@/components/PageHeader.vue'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ id?: string }>()
const { t } = useI18n()
const router = useRouter()
const tenant = useTenantStore()

const step = ref(0)
const submitting = ref(false)
const error = ref<string | null>(null)
const form = reactive({
  name: '',
  description: '',
  method: 'POST',
  url_or_path: 'https://example.com/hooks/daily',
  content_type: 'application/json',
  body: '{"ok":true}',
  schedule_kind: 'daily_at',
  timezone: 'America/Sao_Paulo',
  time: '09:00',
  definition_status: 'draft',
})

const isEdit = computed(() => Boolean(props.id))

const steps = computed(() => [
  t('tasks.wizard.steps.basics'),
  t('tasks.wizard.steps.destination', 'Destination'),
  t('tasks.wizard.steps.schedule'),
  t('tasks.wizard.steps.review'),
])

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

async function onSubmit(activate: boolean): Promise<void> {
  submitting.value = true
  error.value = null

  try {
    const payload = {
      name: form.name,
      description: form.description || null,
      method: form.method,
      url_or_path: form.url_or_path,
      content_type: form.content_type,
      body_template: form.body,
      definition_status: activate ? 'active' : 'draft',
      timezone: form.timezone,
      schedule: {
        kind: form.schedule_kind,
        timezone: form.timezone,
        time: form.time,
      },
      retry_policy: {
        max_attempts: 6,
        strategy: 'standard_exponential',
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
      payload,
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
          {{ $t('tasks.fields.method', 'Method') }}
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
          {{ $t('tasks.fields.url', 'URL') }}
          <input
            v-model="form.url_or_path"
            type="url"
            required
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
          />
        </label>
        <label class="block text-sm font-medium">
          {{ $t('tasks.fields.body', 'JSON body') }}
          <textarea
            v-model="form.body"
            rows="4"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-950"
          />
        </label>
      </div>

      <div v-else-if="step === 2" class="space-y-4">
        <label class="block text-sm font-medium">
          {{ $t('tasks.fields.timezone', 'Timezone') }}
          <input
            v-model="form.timezone"
            type="text"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
          />
        </label>
        <label class="block text-sm font-medium">
          {{ $t('tasks.fields.time', 'Time') }}
          <input
            v-model="form.time"
            type="time"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
          />
        </label>
        <p class="text-sm text-gray-500">
          {{ $t('tasks.wizard.scheduleHint', 'Runs every day at the selected local time.') }}
        </p>
      </div>

      <div v-else class="space-y-2 text-sm">
        <p><strong>{{ $t('tasks.fields.name') }}:</strong> {{ form.name }}</p>
        <p><strong>{{ $t('tasks.fields.method', 'Method') }}:</strong> {{ form.method }}</p>
        <p><strong>{{ $t('tasks.fields.url', 'URL') }}:</strong> {{ form.url_or_path }}</p>
        <p>
          <strong>{{ $t('tasks.fields.schedule', 'Schedule') }}:</strong>
          {{ form.schedule_kind }} @ {{ form.time }} ({{ form.timezone }})
        </p>
      </div>

      <p v-if="error" class="mt-4 text-sm text-red-600" role="alert">{{ error }}</p>

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
          {{ $t('common.next', 'Next') }}
        </button>
        <template v-else>
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-sm"
            :disabled="submitting || !form.name"
            @click="onSubmit(false)"
          >
            {{ $t('tasks.wizard.saveDraft', 'Save draft') }}
          </button>
          <button
            type="button"
            class="rounded-md bg-violet-600 px-4 py-2 text-sm text-white"
            :disabled="submitting || !form.name"
            @click="onSubmit(true)"
          >
            {{ $t('tasks.wizard.activate', 'Save and activate') }}
          </button>
        </template>
      </div>
    </div>
  </div>
</template>
