<script setup lang="ts">
withDefaults(defineProps<{ selected?: boolean }>(), { selected: false })
</script>

<template>
  <div
    class="rounded-lg border p-6"
    :class="selected ? 'border-border-strong bg-surface-emphasis' : 'border-border bg-surface'"
  >
    <slot />
  </div>
</template>
