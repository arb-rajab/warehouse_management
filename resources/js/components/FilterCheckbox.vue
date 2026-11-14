<script setup lang="ts">
defineProps<{
    id: string;
    label: string;
}>();

const model = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="flex items-center gap-2">
        <input
            :id="id"
            v-model="model"
            type="checkbox"
            class="h-4 w-4 rounded border-line-strong"
        />
        <label :for="id" class="text-sm text-ink">{{ label }}</label>
    </div>
</template>
