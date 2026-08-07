<script setup lang="ts">
withDefaults(defineProps<{ selected?: boolean }>(), { selected: false })
</script>

<template>
  <div
    class="min-w-0 rounded-lg border p-6 break-words"
    :class="selected ? 'border-border-strong bg-[var(--color-bg-selected)]' : 'border-border bg-[var(--color-bg-elevated)]'"
  >
    <slot />
  </div>
</template>
