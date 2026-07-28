<script setup lang="ts">
defineProps<{ label?: string }>()
</script>

<template>
  <figure>
    <figcaption v-if="label" class="mb-1 text-sm text-muted">{{ label }}</figcaption>
    <pre
      class="overflow-x-auto rounded-sm border border-border bg-surface p-4 font-mono text-sm text-text"
    ><code><slot /></code></pre>
  </figure>
</template>
