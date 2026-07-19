<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'

import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type {
  AuthMode,
  EndpointProfile,
  EndpointProfilePayload,
  HttpMethod,
  SecretSummary,
} from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ id?: string }>()
const { t } = useI18n()
const router = useRouter()
const tenant = useTenantStore()

const isEdit = computed(() => Boolean(props.id))
const loading = ref(Boolean(props.id))
const submitting = ref(false)
const error = ref<string | null>(null)
const secrets = ref<SecretSummary[]>([])

const methods: HttpMethod[] = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
const authModes: AuthMode[] = [
  'none',
  'static_header',
  'bearer',
  'basic',
  'query_token',
]

interface HeaderRow {
  key: string
  value: string
}

const form = reactive({
  name: '',
  description: '',
  base_url: 'https://',
  method: 'POST' as HttpMethod,
  headers: [] as HeaderRow[],
  auth_mode: 'none' as AuthMode,
  auth_header_name: '',
  auth_query_param: '',
  secret_id: '' as string,
  connect_timeout: 5,
  total_timeout: 15,
  follow_redirects: true,
  verify_tls: true,
  allowed_path_prefix: '',
  enabled: true,
})

const needsSecret = computed(
  () => form.auth_mode !== 'none' && form.auth_mode !== 'static_header',
)
const showAuthHeader = computed(
  () =>
    form.auth_mode === 'static_header' ||
    form.auth_mode === 'bearer' ||
    form.auth_mode === 'basic',
)
const showAuthQuery = computed(() => form.auth_mode === 'query_token')

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

function hydrate(profile: EndpointProfile): void {
  form.name = profile.name
  form.description = profile.description ?? ''
  form.base_url = profile.base_url
  form.method = profile.method
  form.headers = Object.entries(profile.headers ?? {}).map(([key, value]) => ({
    key,
    value,
  }))
  form.auth_mode = profile.auth_mode
  form.auth_header_name = profile.auth_header_name ?? ''
  form.auth_query_param = profile.auth_query_param ?? ''
  form.secret_id = profile.secret_id ?? ''
  form.connect_timeout = profile.connect_timeout
  form.total_timeout = profile.total_timeout
  form.follow_redirects = profile.follow_redirects
  form.verify_tls = profile.verify_tls
  form.allowed_path_prefix = profile.allowed_path_prefix ?? ''
  form.enabled = profile.enabled
}

function buildPayload(): EndpointProfilePayload {
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    base_url: form.base_url.trim(),
    method: form.method,
    headers: headersToRecord(),
    auth_mode: form.auth_mode,
    auth_header_name: showAuthHeader.value
      ? form.auth_header_name.trim() || null
      : null,
    auth_query_param: showAuthQuery.value
      ? form.auth_query_param.trim() || null
      : null,
    secret_id: form.secret_id || null,
    connect_timeout: Number(form.connect_timeout),
    total_timeout: Number(form.total_timeout),
    follow_redirects: form.follow_redirects,
    verify_tls: form.verify_tls,
    allowed_path_prefix: form.allowed_path_prefix.trim() || null,
    enabled: form.enabled,
  }
}

async function loadSecrets(): Promise<void> {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    secrets.value = []
    return
  }
  const { data } = await api.get<{ data: SecretSummary[] }>(
    tenant.tenantPath('/secrets'),
  )
  secrets.value = data.data ?? []
}

async function loadProfile(): Promise<void> {
  if (!props.id) {
    return
  }
  const { data } = await api.get<{ data: EndpointProfile }>(
    tenant.tenantPath(`/endpoint-profiles/${props.id}`),
  )
  hydrate(data.data)
}

async function onSubmit(): Promise<void> {
  submitting.value = true
  error.value = null

  try {
    const payload = buildPayload()

    if (isEdit.value && props.id) {
      await api.patch(
        tenant.tenantPath(`/endpoint-profiles/${props.id}`),
        payload,
      )
      await router.push(`/endpoint-profiles/${props.id}`)
      return
    }

    const { data } = await api.post<{ data: EndpointProfile }>(
      tenant.tenantPath('/endpoint-profiles'),
      payload,
    )
    await router.push(`/endpoint-profiles/${data.data.id}`)
  } catch (err) {
    error.value =
      err instanceof ApiError
        ? err.message
        : t('endpointProfiles.saveError')
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  try {
    await loadSecrets()
    await loadProfile()
  } catch (err) {
    error.value =
      err instanceof ApiError
        ? err.message
        : t('endpointProfiles.loadError')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink
        :to="isEdit && id ? `/endpoint-profiles/${id}` : '/endpoint-profiles'"
        class="text-sm text-violet-600 hover:underline"
      >
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <PageHeader
      :title="
        isEdit
          ? $t('endpointProfiles.editTitle')
          : $t('endpointProfiles.createTitle')
      "
      :subtitle="$t('endpointProfiles.subtitle')"
    />

    <LoadingState v-if="loading" />

    <form
      v-else
      class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
      @submit.prevent="onSubmit"
    >
      <p v-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ error }}
      </p>

      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.name')
          }}</span>
          <input
            v-model="form.name"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.description')
          }}</span>
          <textarea
            v-model="form.description"
            rows="2"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.baseUrl')
          }}</span>
          <input
            v-model="form.base_url"
            type="url"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
          />
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.method')
          }}</span>
          <select
            v-model="form.method"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          >
            <option v-for="method in methods" :key="method" :value="method">
              {{ method }}
            </option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.allowedPathPrefix')
          }}</span>
          <input
            v-model="form.allowed_path_prefix"
            class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            placeholder="/api"
          />
        </label>
      </div>

      <fieldset>
        <legend class="mb-2 text-sm font-medium text-gray-700">
          {{ $t('endpointProfiles.fields.headers') }}
        </legend>
        <div class="space-y-2">
          <div
            v-for="(row, index) in form.headers"
            :key="index"
            class="flex gap-2"
          >
            <input
              v-model="row.key"
              :placeholder="$t('endpointProfiles.fields.headerKey')"
              class="w-1/3 rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <input
              v-model="row.value"
              :placeholder="$t('endpointProfiles.fields.headerValue')"
              class="flex-1 rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <button
              type="button"
              class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
              @click="removeHeader(index)"
            >
              {{ $t('endpointProfiles.fields.removeHeader') }}
            </button>
          </div>
          <button
            type="button"
            class="text-sm text-violet-600 hover:underline"
            @click="addHeader"
          >
            + {{ $t('endpointProfiles.fields.addHeader') }}
          </button>
        </div>
      </fieldset>

      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.authMode')
          }}</span>
          <select
            v-model="form.auth_mode"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          >
            <option v-for="mode in authModes" :key="mode" :value="mode">
              {{ $t(`endpointProfiles.authModes.${mode}`) }}
            </option>
          </select>
        </label>

        <label v-if="showAuthHeader" class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.authHeaderName')
          }}</span>
          <input
            v-model="form.auth_header_name"
            class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            placeholder="Authorization"
          />
        </label>

        <label v-if="showAuthQuery" class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.authQueryParam')
          }}</span>
          <input
            v-model="form.auth_query_param"
            class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            placeholder="access_token"
          />
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.secret')
          }}</span>
          <select
            v-model="form.secret_id"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          >
            <option value="">
              {{ $t('endpointProfiles.fields.secretNone') }}
            </option>
            <option
              v-for="secret in secrets"
              :key="secret.id"
              :value="secret.id"
            >
              {{ secret.name }}
            </option>
          </select>
          <span
            v-if="needsSecret"
            class="mt-1 block text-xs text-gray-500"
          >{{ $t('endpointProfiles.fields.secretHint') }}</span>
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.connectTimeout')
          }}</span>
          <input
            v-model.number="form.connect_timeout"
            type="number"
            min="1"
            max="300"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">{{
            $t('endpointProfiles.fields.totalTimeout')
          }}</span>
          <input
            v-model.number="form.total_timeout"
            type="number"
            min="1"
            max="600"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>
      </div>

      <div class="flex flex-wrap gap-6">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.follow_redirects" type="checkbox" class="rounded" />
          {{ $t('endpointProfiles.fields.followRedirects') }}
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.verify_tls" type="checkbox" class="rounded" />
          {{ $t('endpointProfiles.fields.verifyTls') }}
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.enabled" type="checkbox" class="rounded" />
          {{ $t('endpointProfiles.fields.enabled') }}
        </label>
      </div>

      <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
        <RouterLink
          :to="isEdit && id ? `/endpoint-profiles/${id}` : '/endpoint-profiles'"
          class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          {{ $t('common.cancel') }}
        </RouterLink>
        <button
          type="submit"
          :disabled="submitting"
          class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-60"
        >
          {{
            submitting ? $t('common.loading') : $t('endpointProfiles.save')
          }}
        </button>
      </div>
    </form>
  </div>
</template>
