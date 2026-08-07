<script setup lang="ts">
import BidiText from '@/components/ui/BidiText.vue'

defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <header class="page-header">
    <div class="min-w-0 flex-1">
      <h1><BidiText :value="title" /></h1>
      <p v-if="subtitle" class="mt-1 text-sm text-muted">
        {{ subtitle }}
      </p>
    </div>
    <div v-if="$slots.actions" class="page-header__actions flex max-w-full flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>
