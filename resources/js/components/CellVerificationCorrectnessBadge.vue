<script setup lang="ts">
import { t } from '@/lib/i18n';

defineProps<{
    isCorrect: boolean;
}>();
</script>

<template>
    <span
        v-if="isCorrect"
        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
    >
        {{ t('cellVerificationReport.correct') }}
    </span>
    <span
        v-else
        class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300"
    >
        {{ t('cellVerificationReport.incorrect') }}
    </span>
</template>
