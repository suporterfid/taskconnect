<script setup lang="ts">
import { ref } from 'vue'

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

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const composing = ref(false)

function emitValue(event: Event): void {
  if (composing.value) return
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}

function finishComposition(event: Event): void {
  composing.value = false
  emitValue(event)
}
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
    class="base-control min-h-11 min-w-0 w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-text placeholder:text-muted disabled:cursor-not-allowed"
    :class="ariaInvalid === 'true' ? 'invalid-control' : undefined"
    @compositionstart="composing = true"
    @compositionend="finishComposition"
    @input="emitValue"
  />
</template>
