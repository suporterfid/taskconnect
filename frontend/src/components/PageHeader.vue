<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1>{{ title }}</h1>
      <p v-if="subtitle" class="mt-1 text-sm text-muted">
        {{ subtitle }}
      </p>
    </div>
    <div v-if="$slots.actions" class="shrink-0">
      <slot name="actions" />
    </div>
  </header>
</template>
