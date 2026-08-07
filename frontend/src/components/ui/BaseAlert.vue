<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import { semanticIcons } from '@/utils/icons'
import type { Tone } from '@/utils/statusTone'

withDefaults(
  defineProps<{
    tone?: Tone
    // 'alert' (assertive) is right for errors/warnings that interrupt; a
    // success confirmation after a user action reads better as 'status'
    // (polite) — it's still announced, just not treated as an interruption.
    role?: 'alert' | 'status'
  }>(),
  { tone: 'info', role: 'alert' },
)

// Same tone -> icon pairing as the status system (#90), so an alert and a
// badge for the same condition never disagree about which glyph means what.
const toneIcon: Record<Tone, keyof typeof semanticIcons> = {
  success: 'success',
  warning: 'warning',
  danger: 'danger',
  info: 'info',
  neutral: 'neutral',
}

const toneClasses: Record<Tone, string> = {
  success: 'status-success border-[var(--color-success-border)] bg-[var(--color-success-bg)] text-[var(--color-success-fg)]',
  warning: 'status-warning border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] text-[var(--color-warning-fg)]',
  danger: 'status-danger border-[var(--color-danger-border)] bg-[var(--color-danger-bg)] text-[var(--color-danger-fg)]',
  info: 'status-info border-[var(--color-info-border)] bg-[var(--color-info-bg)] text-[var(--color-info-fg)]',
  neutral: 'border-border bg-surface-emphasis text-text',
}
</script>

<template>
  <div :role="role" class="flex items-start gap-2 rounded-lg border p-4 text-sm break-words" :class="toneClasses[tone]">
    <AppIcon :icon="semanticIcons[toneIcon[tone]]" :size="16" class="mt-0.5" />
    <div class="min-w-0"><slot /></div>
  </div>
</template>
