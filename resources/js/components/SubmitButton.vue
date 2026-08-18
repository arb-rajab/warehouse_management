<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';

defineProps<{
    label: string;
    processingLabel: string;
    processing: boolean;
}>();
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        class="flex w-full items-center justify-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200"
    >
        <LoaderCircle v-if="processing" class="h-4 w-4 shrink-0 animate-spin" />
        {{ processing ? processingLabel : label }}
    </button>
</template>
