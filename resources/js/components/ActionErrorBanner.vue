<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        role="alert"
        class="mb-4 flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-900 dark:bg-red-950 dark:text-red-400"
    >
        <CircleAlert class="h-4 w-4 shrink-0" />
        {{ message }}
    </p>
</template>
