<script setup lang="ts">
import { Check } from '@lucide/vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <button
        type="button"
        class="mt-1 flex cursor-pointer items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
    >
        <Check class="h-3 w-3 shrink-0" />
        {{ t('cellVerificationReport.accept') }}
    </button>
</template>
