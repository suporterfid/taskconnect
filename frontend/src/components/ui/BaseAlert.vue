<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import { semanticIcons } from '@/utils/icons'
import type { Tone } from '@/utils/statusTone'

withDefaults(defineProps<{ tone?: Tone }>(), { tone: 'info' })

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
  success: 'border-success/30 bg-success/10 text-success',
  warning: 'border-warning/30 bg-warning/10 text-warning',
  danger: 'border-danger/30 bg-danger/10 text-danger',
  info: 'border-info/30 bg-info/10 text-info',
  neutral: 'border-border bg-surface-emphasis text-text',
}
</script>

<template>
  <div role="alert" class="flex items-start gap-2 rounded-lg border p-4 text-sm" :class="toneClasses[tone]">
    <AppIcon :icon="semanticIcons[toneIcon[tone]]" :size="16" class="mt-0.5" />
    <div><slot /></div>
  </div>
</template>
