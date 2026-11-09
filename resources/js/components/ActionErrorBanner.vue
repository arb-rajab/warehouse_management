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
        class="mb-4 flex items-center gap-1.5 rounded-md border border-danger/30 bg-danger-soft px-3 py-2 text-sm text-danger-strong"
    >
        <CircleAlert class="h-4 w-4 shrink-0" />
        {{ message }}
    </p>
</template>
