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
  success: 'status-success border-[var(--color-success-border)] bg-[var(--color-success-bg)] text-[var(--color-success-fg)]',
  warning: 'status-warning border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] text-[var(--color-warning-fg)]',
  danger: 'status-danger border-[var(--color-danger-border)] bg-[var(--color-danger-bg)] text-[var(--color-danger-fg)]',
  info: 'status-info border-[var(--color-info-border)] bg-[var(--color-info-bg)] text-[var(--color-info-fg)]',
  neutral: 'bg-surface-emphasis text-text',
}
</script>

<template>
  <span
    class="inline-flex max-w-full items-center gap-1 rounded-pill border px-2.5 py-0.5 text-xs font-medium whitespace-normal break-words"
    :class="toneClasses[tone]"
  >
    <AppIcon v-if="icon" :icon="icon" :size="16" />
    {{ label }}
  </span>
</template>
