<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue'
</script>

<template>
  <div>
    <PageHeader :title="$t('settings.title')" :subtitle="$t('settings.subtitle')" />

    <div class="space-y-6">
      <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-medium">{{ $t('settings.sections.general') }}</h2>
        <p class="mt-2 text-sm text-gray-500">
          Tenant settings will be available when the API is ready.
        </p>
      </section>
    </div>
  </div>
</template>
