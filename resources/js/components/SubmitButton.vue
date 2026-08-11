<script setup lang="ts">
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
        class="w-full rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200"
    >
        {{ processing ? processingLabel : label }}
    </button>
</template>
