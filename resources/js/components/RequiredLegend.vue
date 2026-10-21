<script setup lang="ts">
import { t } from '@/lib/i18n';
</script>

<template>
    <p data-required-legend class="text-sm text-gray-600 dark:text-neutral-400">
        <span aria-hidden="true" class="text-red-700 dark:text-red-400">*</span>
        {{ t('common.requiredLegend') }}
    </p>
</template>
