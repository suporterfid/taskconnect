<script setup lang="ts">
import { computed } from 'vue'
import { Loader } from 'lucide-vue-next'

import AppIcon from '@/components/AppIcon.vue'

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'tertiary' | 'danger'
    size?: 'md' | 'sm'
    type?: 'button' | 'submit' | 'reset'
    loading?: boolean
    disabled?: boolean
  }>(),
  {
    variant: 'primary',
    size: 'md',
    type: 'button',
    loading: false,
    disabled: false,
  },
)

const isDisabled = computed(() => props.disabled || props.loading)

const variantClasses: Record<NonNullable<typeof props.variant>, string> = {
  primary: 'border border-transparent bg-[var(--color-action-primary)] text-[var(--color-action-primary-content)] hover:bg-[var(--color-action-primary-hover)] active:bg-[var(--color-action-primary-active)]',
  secondary: 'border border-border-strong bg-surface text-text hover:bg-surface-emphasis active:bg-[var(--color-bg-selected)]',
  tertiary: 'border border-transparent bg-transparent text-action-text underline hover:bg-surface-emphasis active:bg-[var(--color-bg-selected)]',
  // Destructive actions use the explicit danger foreground/background/border
  // triplet, so meaning and contrast never depend on an opacity construction.
  danger: 'status-danger border border-[var(--color-danger-border)] bg-[var(--color-danger-bg)] text-[var(--color-danger-fg)] hover:border-[var(--color-danger-fg)] active:border-2',
}

const sizeClasses: Record<NonNullable<typeof props.size>, string> = {
  // 44x44 minimum touch target (§7).
  md: 'min-h-11 min-w-11 px-4 py-2 text-sm',
  // Dense table rows keep compact padding without shrinking the hit target.
  sm: 'min-h-11 min-w-11 px-3 py-1 text-sm',
}
</script>

<template>
  <button
    :type="type"
    :disabled="isDisabled"
    :aria-disabled="isDisabled ? 'true' : undefined"
    :aria-busy="loading ? 'true' : undefined"
    class="base-button inline-flex max-w-full items-center justify-center gap-2 rounded-md font-medium whitespace-normal break-words text-center transition-colors duration-standard ease-standard disabled:cursor-not-allowed"
    :class="[variantClasses[variant], sizeClasses[size]]"
  >
    <AppIcon v-if="loading" :icon="Loader" :size="16" class="animate-spin" />
    <slot />
  </button>
</template>
