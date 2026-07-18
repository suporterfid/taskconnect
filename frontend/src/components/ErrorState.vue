<script setup lang="ts">
defineProps<{
  message: string
}>()

const emit = defineEmits<{
  retry: []
}>()
</script>

<template>
  <div
    class="rounded-lg border border-red-200 bg-red-50 p-6 text-center dark:border-red-900 dark:bg-red-950/30"
    role="alert"
  >
    <p class="text-red-700 dark:text-red-300">{{ message }}</p>
    <button
      type="button"
      class="mt-4 rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
      @click="emit('retry')"
    >
      {{ $t('common.retry') }}
    </button>
  </div>
</template>
