<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string
    id?: string
    disabled?: boolean
    required?: boolean
    describedBy?: string
    ariaInvalid?: 'true' | 'false'
  }>(),
  {},
)

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <select
    :id="id"
    :value="modelValue"
    :disabled="disabled"
    :required="required"
    :aria-describedby="describedBy"
    :aria-invalid="ariaInvalid"
    class="base-control min-h-11 w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-text disabled:cursor-not-allowed"
    :class="ariaInvalid === 'true' ? 'invalid-control' : undefined"
    @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
  >
    <slot />
  </select>
</template>
