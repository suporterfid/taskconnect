<script setup lang="ts">
import { usePreferredReducedMotion } from '@vueuse/core'

defineProps<{
  message?: string
}>()

const prefersReducedMotion = usePreferredReducedMotion()
</script>

<template>
  <div class="flex items-center justify-center py-16" role="status">
    <!--
      Reduced motion gets a solid, static ring rather than a frozen spinner
      arc (a spinner whose animation is merely shortened to ~0ms reads as a
      broken circle, not a loading indicator) — see #97.
    -->
    <div
      class="loading-indicator h-8 w-8 rounded-full border-2 border-[var(--color-action-primary)]"
      :class="prefersReducedMotion === 'reduce' ? '' : 'animate-spin border-t-transparent'"
      aria-hidden="true"
    />
    <span class="sr-only">{{ message ?? $t('common.loading') }}</span>
  </div>
</template>
