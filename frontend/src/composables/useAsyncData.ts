import { storeToRefs } from 'pinia'
import { onMounted, ref, watch, type Ref } from 'vue'

import { i18n } from '@/i18n'
import { ApiError } from '@/services/api'
import { useTenantStore } from '@/stores/tenant'

export function useAsyncData<T>(loader: () => Promise<T>) {
  const data: Ref<T | null> = ref(null)
  const loading = ref(true)
  const error = ref<string | null>(null)

  const tenant = useTenantStore()
  const { currentTenantId, currentEnvironmentId } = storeToRefs(tenant)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      data.value = await loader()
    } catch (err) {
      data.value = null
      error.value =
        err instanceof ApiError
          ? err.message
          : i18n.global.t('common.errors.requestFailed')
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  watch([currentTenantId, currentEnvironmentId], () => {
    void load()
  })

  return { data, loading, error, reload: load }
}
