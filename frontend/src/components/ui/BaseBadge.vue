<script setup lang="ts">
import type { FunctionalComponent } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import type { Tone } from '@/utils/statusTone'

// `label` is required (not a default slot) so a badge can never ship with only
// a color/icon and no text — color is never the sole carrier of meaning (§3.3).
withDefaults(
  defineProps<{
    label: string
    tone?: Tone
    icon?: FunctionalComponent
  }>(),
  { tone: 'neutral' },
)

// `neutral` deliberately stays a plain surface tint, not a tinted status
// color — archived/revoked/cancelled are terminal-but-benign, not an accent
// worth drawing the eye to (§3.2).
const toneClasses: Record<Tone, string> = {
  success: 'bg-success/15 text-success',
  warning: 'bg-warning/15 text-warning',
  danger: 'bg-danger/15 text-danger',
  info: 'bg-info/15 text-info',
  neutral: 'bg-surface-emphasis text-text',
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-pill px-2.5 py-0.5 text-xs font-medium"
    :class="toneClasses[tone]"
  >
    <AppIcon v-if="icon" :icon="icon" :size="16" />
    {{ label }}
  </span>
</template>
