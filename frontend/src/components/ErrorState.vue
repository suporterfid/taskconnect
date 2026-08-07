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
  <div class="status-danger rounded-lg border p-6 text-center break-words" role="alert">
    <p class="flex items-center justify-center gap-2">
      <AppIcon :icon="semanticIcons.danger" :size="16" />
      {{ message }}
    </p>
    <BaseButton variant="danger" class="mt-4" @click="emit('retry')">
      {{ $t('common.retry') }}
    </BaseButton>
  </div>
</template>
