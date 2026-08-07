<script setup lang="ts">
defineProps<{ value?: string | number | null }>()
</script>

<template>
  <bdi dir="auto"><slot>{{ value }}</slot></bdi>
</template>
