import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'

import api, { ApiError } from '@/services/api'
import type { Environment, Tenant } from '@/services/types'

const TENANT_KEY = 'taskconnect.tenant'
const ENV_KEY = 'taskconnect.environment'

function isActiveEnvironment(env: Environment): boolean {
  return !env.archived_at
}

export const useTenantStore = defineStore('tenant', () => {
  const tenants = ref<Tenant[]>([])
  const environments = ref<Environment[]>([])
  const currentTenantId = ref<string | null>(localStorage.getItem(TENANT_KEY))
  const currentEnvironmentId = ref<string | null>(localStorage.getItem(ENV_KEY))
  const loading = ref(false)
  const error = ref<string | null>(null)

  const currentTenant = computed(
    () => tenants.value.find((t) => t.id === currentTenantId.value) ?? null,
  )

  const currentEnvironment = computed(
    () =>
      environments.value.find((e) => e.id === currentEnvironmentId.value) ??
      null,
  )

  const activeEnvironments = computed(() =>
    environments.value.filter(isActiveEnvironment),
  )

  watch(currentTenantId, (id) => {
    if (id) {
      localStorage.setItem(TENANT_KEY, id)
    } else {
      localStorage.removeItem(TENANT_KEY)
    }
  })

  watch(currentEnvironmentId, (id) => {
    if (id) {
      localStorage.setItem(ENV_KEY, id)
    } else {
      localStorage.removeItem(ENV_KEY)
    }
  })

  function selectActiveEnvironment(): void {
    const active = activeEnvironments.value
    const current = environments.value.find(
      (e) => e.id === currentEnvironmentId.value,
    )

    if (!current || !isActiveEnvironment(current)) {
      currentEnvironmentId.value = active[0]?.id ?? null
    }
  }

  async function fetchTenants(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get<{ data: Tenant[] }>('/tenants')
      tenants.value = data.data ?? []

      if (!currentTenantId.value && tenants.value.length > 0) {
        currentTenantId.value = tenants.value[0]!.id
      }

      if (currentTenantId.value) {
        await fetchEnvironments(currentTenantId.value)
      }
    } catch (err) {
      error.value =
        err instanceof ApiError ? err.message : 'Failed to load tenants'
    } finally {
      loading.value = false
    }
  }

  async function fetchEnvironments(tenantId: string): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get<{ data: Environment[] }>(
        `/tenants/${tenantId}/environments`,
      )
      environments.value = data.data ?? []
      selectActiveEnvironment()
    } catch (err) {
      environments.value = []
      currentEnvironmentId.value = null
      error.value =
        err instanceof ApiError ? err.message : 'Failed to load environments'
    } finally {
      loading.value = false
    }
  }

  async function setTenant(tenantId: string): Promise<void> {
    currentTenantId.value = tenantId
    currentEnvironmentId.value = null
    await fetchEnvironments(tenantId)
  }

  function setEnvironment(environmentId: string): void {
    currentEnvironmentId.value = environmentId
  }

  function tenantPath(path: string): string {
    const tenantId = currentTenantId.value
    const envId = currentEnvironmentId.value
    if (!tenantId || !envId) {
      return path
    }
    return `/tenants/${tenantId}/environments/${envId}${path}`
  }

  return {
    tenants,
    environments,
    activeEnvironments,
    currentTenantId,
    currentEnvironmentId,
    currentTenant,
    currentEnvironment,
    loading,
    error,
    fetchTenants,
    fetchEnvironments,
    setTenant,
    setEnvironment,
    tenantPath,
  }
})
