<script setup lang="ts">
import type { FunctionalComponent } from 'vue'

/**
 * Thin wrapper around the icon family (lucide-vue-next) so every icon in the
 * app gets the same size scale and the same accessible defaults: decorative
 * by default (aria-hidden, no accessible name), or role="img" + aria-label
 * when `label` is passed for a meaningful icon with no adjacent text. See
 * #96.
 */
withDefaults(
  defineProps<{
    icon: FunctionalComponent
    size?: 16 | 20 | 24
    label?: string
    directional?: boolean
  }>(),
  { size: 20 },
)
</script>

<template>
  <component
    :is="icon"
    :size="size"
    :aria-hidden="label ? undefined : 'true'"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    class="inline-block shrink-0"
    :class="{ 'app-icon--directional': directional }"
  />
</template>

<style scoped>
:global([dir='rtl'] .app-icon--directional) {
  transform: scaleX(-1);
}
</style>
