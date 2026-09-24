<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';

defineProps<{
    label: string;
    processingLabel: string;
    processing: boolean;
    form?: string;
}>();
</script>

<template>
    <button
        type="submit"
        :form="form"
        :disabled="processing"
        class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200"
    >
        <LoaderCircle v-if="processing" class="h-4 w-4 shrink-0 animate-spin" />
        {{ processing ? processingLabel : label }}
    </button>
</template>
