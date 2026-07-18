<script setup lang="ts">
defineProps<{
  message?: string
}>()
</script>

<template>
  <div class="flex items-center justify-center py-16" role="status">
    <div
      class="h-8 w-8 animate-spin rounded-full border-2 border-violet-600 border-t-transparent"
      aria-hidden="true"
    />
    <span class="sr-only">{{ message ?? $t('common.loading') }}</span>
  </div>
</template>
