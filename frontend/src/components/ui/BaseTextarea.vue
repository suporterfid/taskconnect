<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string
    id?: string
    placeholder?: string
    disabled?: boolean
    required?: boolean
    rows?: number
    describedBy?: string
    ariaInvalid?: 'true' | 'false'
  }>(),
  { rows: 3 },
)

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <textarea
    :id="id"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :required="required"
    :rows="rows"
    :aria-describedby="describedBy"
    :aria-invalid="ariaInvalid"
    class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-text placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-60"
    :class="ariaInvalid ? 'border-danger' : 'border-border'"
    @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
  />
</template>
