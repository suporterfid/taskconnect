import { onMounted, ref, type Ref } from 'vue'

import { ApiError } from '@/services/api'

export function useAsyncData<T>(loader: () => Promise<T>) {
  const data: Ref<T | null> = ref(null)
  const loading = ref(true)
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      data.value = await loader()
    } catch (err) {
      data.value = null
      error.value =
        err instanceof ApiError ? err.message : 'Request failed'
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  return { data, loading, error, reload: load }
}
