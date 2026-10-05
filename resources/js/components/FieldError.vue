<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        class="mt-1 flex items-center gap-1 text-sm text-red-600 dark:text-red-400"
    >
        <CircleAlert class="h-3.5 w-3.5 shrink-0" />
        {{ message }}
    </p>
</template>
