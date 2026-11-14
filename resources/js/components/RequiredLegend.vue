<script setup lang="ts">
import { t } from '@/lib/i18n';
</script>

<template>
    <p data-required-legend class="text-sm text-muted">
        <span aria-hidden="true" class="text-danger-strong">*</span>
        {{ t('common.requiredLegend') }}
    </p>
</template>
