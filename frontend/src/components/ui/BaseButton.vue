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
  primary: 'bg-action text-white hover:bg-action-hover',
  secondary: 'border border-border bg-transparent text-text hover:border-border-strong',
  tertiary: 'bg-transparent text-action-text hover:text-action-hover hover:underline',
  // Deliberately bg-danger-strong, not bg-danger: white text on the
  // foreground-optimized `danger` red is only 2.77:1 (fails); on
  // `danger-strong` it's 4.83:1. See the token comment in style.css.
  danger: 'bg-danger-strong text-white hover:bg-danger-strong-hover',
}

const sizeClasses: Record<NonNullable<typeof props.size>, string> = {
  // 44x44 minimum touch target (§7).
  md: 'min-h-11 min-w-11 px-4 py-2 text-sm',
  // Dense table rows only — still meets the 24x24 CSS px floor (§7).
  sm: 'min-h-6 px-3 py-1 text-sm',
}
</script>

<template>
  <button
    :type="type"
    :disabled="isDisabled"
    :aria-disabled="isDisabled ? 'true' : undefined"
    :aria-busy="loading ? 'true' : undefined"
    class="inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors duration-standard ease-standard disabled:cursor-not-allowed disabled:opacity-60"
    :class="[variantClasses[variant], sizeClasses[size]]"
  >
    <AppIcon v-if="loading" :icon="Loader" :size="16" class="animate-spin" />
    <slot />
  </button>
</template>
