<script setup lang="ts">
defineProps<{
    title: string;
}>();
</script>

<template>
    <div
        class="mb-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-3"
    >
        <h1 class="text-xl font-semibold">{{ title }}</h1>
        <slot />
    </div>
</template>
