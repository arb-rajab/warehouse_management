<script setup lang="ts">
defineProps<{
    title: string;
}>();
</script>

<template>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ title }}</h1>
        <slot />
    </div>
</template>
