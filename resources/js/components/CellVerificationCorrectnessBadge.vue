<script setup lang="ts">
import { t } from '@/lib/i18n';

defineProps<{
    isCorrect: boolean;
}>();
</script>

<template>
    <span
        v-if="isCorrect"
        class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-xs font-medium text-success-strong"
    >
        {{ t('cellVerificationReport.correct') }}
    </span>
    <span
        v-else
        class="inline-flex items-center gap-1 rounded-full bg-danger-soft px-2 py-0.5 text-xs font-medium text-danger-strong"
    >
        {{ t('cellVerificationReport.incorrect') }}
    </span>
</template>
