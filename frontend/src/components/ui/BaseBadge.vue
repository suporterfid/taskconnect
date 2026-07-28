<script setup lang="ts">
import type { FunctionalComponent } from 'vue'

import AppIcon from '@/components/AppIcon.vue'

// `label` is required (not a default slot) so a badge can never ship with only
// a color/icon and no text — color is never the sole carrier of meaning (§3.3).
withDefaults(
  defineProps<{
    label: string
    tone?: 'neutral' | 'danger'
    icon?: FunctionalComponent
  }>(),
  { tone: 'neutral' },
)
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-pill px-2.5 py-0.5 text-xs font-medium"
    :class="tone === 'danger' ? 'bg-danger/15 text-danger' : 'bg-surface-emphasis text-text'"
  >
    <AppIcon v-if="icon" :icon="icon" :size="16" />
    {{ label }}
  </span>
</template>
