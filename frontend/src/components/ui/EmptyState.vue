<script setup lang="ts">
defineProps<{ message: string }>()
</script>

<template>
  <div class="empty-state rounded-lg border border-dashed border-border text-center text-sm text-muted">
    <slot>{{ message }}</slot>
  </div>
</template>
