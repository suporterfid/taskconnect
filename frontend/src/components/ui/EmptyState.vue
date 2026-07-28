<script setup lang="ts">
defineProps<{ message: string }>()
</script>

<template>
  <div class="rounded-lg border border-dashed border-border p-12 text-center text-sm text-muted">
    <slot>{{ message }}</slot>
  </div>
</template>
