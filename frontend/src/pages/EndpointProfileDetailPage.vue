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
import type { EndpointProfile, EndpointTestResult } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const router = useRouter()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: EndpointProfile }>(
    tenant.tenantPath(`/endpoint-profiles/${props.id}`),
  )
  return response.data
})

const headerEntries = computed(() =>
  Object.entries(data.value?.headers ?? {}),
)

const archiving = ref(false)
const testing = ref(false)
const actionError = ref<string | null>(null)
const testPath = ref('')
const testBody = ref('')
const testResult = ref<EndpointTestResult | null>(null)

async function onArchive(): Promise<void> {
  if (!confirm(t('endpointProfiles.archiveConfirm'))) {
    return
  }
  archiving.value = true
  actionError.value = null
  try {
    await api.delete(tenant.tenantPath(`/endpoint-profiles/${props.id}`))
    await router.push('/endpoint-profiles')
  } catch (err) {
    actionError.value =
      err instanceof ApiError
        ? err.message
        : t('endpointProfiles.archiveError')
  } finally {
    archiving.value = false
  }
}

async function onTest(): Promise<void> {
  testing.value = true
  actionError.value = null
  testResult.value = null
  try {
    const payload: { path?: string; body?: string } = {}
    if (testPath.value.trim()) {
      payload.path = testPath.value.trim()
    }
    if (testBody.value.trim()) {
      payload.body = testBody.value
    }
    const { data: response } = await api.post<{ data: EndpointTestResult }>(
      tenant.tenantPath(`/endpoint-profiles/${props.id}/test`),
      payload,
    )
    testResult.value = response.data
  } catch (err) {
    actionError.value =
      err instanceof ApiError
        ? err.message
        : t('endpointProfiles.testError')
  } finally {
    testing.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink
        to="/endpoint-profiles"
        class="text-sm text-violet-600 hover:underline"
      >
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('endpointProfiles.loadError')"
      @retry="reload"
    />
    <template v-else-if="data">
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <PageHeader
          :title="data.name"
          :subtitle="$t('endpointProfiles.detail.title')"
        />
        <div class="flex flex-wrap gap-2">
          <RouterLink
            :to="`/endpoint-profiles/${data.id}/edit`"
            class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
          >
            {{ $t('common.edit') }}
          </RouterLink>
          <button
            type="button"
            :disabled="archiving"
            class="rounded-md border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-60"
            @click="onArchive"
          >
            {{ $t('endpointProfiles.archive') }}
          </button>
        </div>
      </div>

      <p
        v-if="actionError"
        class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
      >
        {{ actionError }}
      </p>

      <dl
        class="mb-6 grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2"
      >
        <div class="sm:col-span-2">
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.detail.baseUrl') }}
          </dt>
          <dd class="mt-1 break-all font-mono text-sm">{{ data.base_url }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.fields.method') }}
          </dt>
          <dd class="mt-1 font-mono text-sm">{{ data.method }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.status') }}</dt>
          <dd class="mt-1 text-sm">
            {{
              data.enabled
                ? $t('endpointProfiles.enabled')
                : $t('endpointProfiles.disabled')
            }}
          </dd>
        </div>
        <div v-if="data.description" class="sm:col-span-2">
          <dt class="text-sm text-gray-500">
            {{ $t('common.description') }}
          </dt>
          <dd class="mt-1 text-sm">{{ data.description }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.detail.auth') }}
          </dt>
          <dd class="mt-1 text-sm">
            {{ $t(`endpointProfiles.authModes.${data.auth_mode}`) }}
            <span
              v-if="data.auth_header_name"
              class="ml-1 font-mono text-gray-500"
            >{{ data.auth_header_name }}</span>
            <span
              v-if="data.auth_query_param"
              class="ml-1 font-mono text-gray-500"
            >?{{ data.auth_query_param }}</span>
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.detail.secret') }}
          </dt>
          <dd class="mt-1 font-mono text-sm">
            {{ data.secret_id || $t('endpointProfiles.detail.none') }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.detail.timeouts') }}
          </dt>
          <dd class="mt-1 text-sm">
            {{ data.connect_timeout }}s /
            {{ data.total_timeout }}s
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.detail.security') }}
          </dt>
          <dd class="mt-1 text-sm">
            TLS:
            {{
              data.verify_tls
                ? $t('endpointProfiles.fields.verifyTls')
                : $t('endpointProfiles.disabled')
            }}
            ·
            {{
              data.follow_redirects
                ? $t('endpointProfiles.fields.followRedirects')
                : $t('endpointProfiles.disabled')
            }}
          </dd>
        </div>
        <div v-if="data.allowed_path_prefix" class="sm:col-span-2">
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.fields.allowedPathPrefix') }}
          </dt>
          <dd class="mt-1 font-mono text-sm">
            {{ data.allowed_path_prefix }}
          </dd>
        </div>
        <div class="sm:col-span-2">
          <dt class="text-sm text-gray-500">
            {{ $t('endpointProfiles.detail.headers') }}
          </dt>
          <dd class="mt-1">
            <p v-if="!headerEntries.length" class="text-sm text-gray-500">
              {{ $t('endpointProfiles.detail.noHeaders') }}
            </p>
            <ul v-else class="space-y-1 font-mono text-sm">
              <li v-for="[key, value] in headerEntries" :key="key">
                <span class="text-gray-500">{{ key }}:</span>
                {{ value }}
              </li>
            </ul>
          </dd>
        </div>
      </dl>

      <section
        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
      >
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
          {{ $t('endpointProfiles.test.title') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          {{ $t('endpointProfiles.test.subtitle') }}
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-gray-700">{{
              $t('endpointProfiles.test.path')
            }}</span>
            <input
              v-model="testPath"
              class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
              :placeholder="$t('endpointProfiles.test.pathPlaceholder')"
            />
          </label>
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-gray-700">{{
              $t('endpointProfiles.test.body')
            }}</span>
            <textarea
              v-model="testBody"
              rows="3"
              class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
          </label>
        </div>

        <button
          type="button"
          :disabled="testing"
          class="mt-4 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-60"
          @click="onTest"
        >
          {{
            testing
              ? $t('endpointProfiles.test.running')
              : $t('endpointProfiles.test.run')
          }}
        </button>

        <div
          v-if="testResult"
          class="mt-6 space-y-3 rounded-md border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
        >
          <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
            {{ $t('endpointProfiles.test.result') }}
          </h3>
          <p class="text-sm">
            <span class="text-gray-500">{{ $t('endpointProfiles.test.status') }}:</span>
            {{
              testResult.response_status ??
                $t('endpointProfiles.test.noStatus')
            }}
          </p>
          <p class="break-all font-mono text-sm">
            <span class="text-gray-500">{{ $t('endpointProfiles.test.url') }}:</span>
            {{ testResult.request_url_redacted }}
          </p>
          <p
            v-if="testResult.transport_error_code"
            class="text-sm text-red-700"
          >
            <span class="font-medium">{{
              $t('endpointProfiles.test.transportError')
            }}:</span>
            {{ testResult.transport_error_code }}
          </p>
          <div v-if="testResult.request_headers_redacted">
            <p class="text-sm text-gray-500">
              {{ $t('endpointProfiles.test.responseHeaders') }}
            </p>
            <pre
              class="mt-1 overflow-x-auto rounded bg-white p-2 font-mono text-xs dark:bg-gray-900"
            >{{ JSON.stringify(testResult.request_headers_redacted, null, 2) }}</pre>
          </div>
          <div v-if="testResult.response_body_truncated">
            <p class="text-sm text-gray-500">
              {{ $t('endpointProfiles.test.responseBody') }}
            </p>
            <pre
              class="mt-1 max-h-64 overflow-auto rounded bg-white p-2 font-mono text-xs dark:bg-gray-900"
            >{{ testResult.response_body_truncated }}</pre>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
