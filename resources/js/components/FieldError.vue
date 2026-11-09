<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <p v-if="message" class="mt-1 flex items-center gap-1 text-sm text-danger">
        <CircleAlert class="h-3.5 w-3.5 shrink-0" />
        {{ message }}
    </p>
</template>
