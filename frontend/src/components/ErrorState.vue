<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { semanticIcons } from '@/utils/icons'

defineProps<{
  message: string
}>()

const emit = defineEmits<{
  retry: []
}>()
</script>

<template>
  <div class="rounded-lg border border-danger/30 bg-danger/10 p-6 text-center" role="alert">
    <p class="flex items-center justify-center gap-2 text-danger">
      <AppIcon :icon="semanticIcons.danger" :size="16" />
      {{ message }}
    </p>
    <BaseButton variant="danger" class="mt-4" @click="emit('retry')">
      {{ $t('common.retry') }}
    </BaseButton>
  </div>
</template>
