<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string
    id?: string
    type?: string
    placeholder?: string
    autocomplete?: string
    disabled?: boolean
    required?: boolean
    describedBy?: string
    ariaInvalid?: 'true' | 'false'
  }>(),
  { type: 'text' },
)

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <input
    :id="id"
    :type="type"
    :value="modelValue"
    :placeholder="placeholder"
    :autocomplete="autocomplete"
    :disabled="disabled"
    :required="required"
    :aria-describedby="describedBy"
    :aria-invalid="ariaInvalid"
    class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-text placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-60"
    :class="ariaInvalid ? 'border-danger' : 'border-border'"
    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
  />
</template>
