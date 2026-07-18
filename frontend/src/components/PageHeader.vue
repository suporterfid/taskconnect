<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <header class="mb-8">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
      {{ title }}
    </h1>
    <p v-if="subtitle" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
      {{ subtitle }}
    </p>
  </header>
</template>
