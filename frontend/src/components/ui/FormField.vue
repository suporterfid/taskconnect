<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { semanticIcons } from '@/utils/icons'

const props = defineProps<{
  id: string
  label: string
  hint?: string
  error?: string
  required?: boolean
}>()

// Only referenced by aria-describedby when it will actually render (below) —
// otherwise error visible would leave a dangling id reference to nothing.
const hintId = computed(() => (props.hint && !props.error ? `${props.id}-hint` : undefined))
const errorId = computed(() => (props.error ? `${props.id}-error` : undefined))
const describedBy = computed(() => [hintId.value, errorId.value].filter(Boolean).join(' ') || undefined)
const ariaInvalid = computed(() => (props.error ? 'true' : undefined))
</script>

<template>
  <div class="space-y-1">
    <label :for="id" class="block text-sm font-medium text-text">
      {{ label }}
      <span v-if="required" aria-hidden="true" class="text-danger">*</span>
    </label>

    <!-- Bind describedBy/ariaInvalid onto the nested Base* input. -->
    <slot :described-by="describedBy" :aria-invalid="ariaInvalid" />

    <p v-if="hint && !error" :id="hintId" class="text-sm text-muted">{{ hint }}</p>
    <p v-if="error" :id="errorId" role="alert" class="flex items-center gap-1 text-sm text-danger">
      <AppIcon :icon="semanticIcons.danger" :size="16" />
      {{ error }}
    </p>
  </div>
</template>
