<script setup lang="ts">
withDefaults(
    defineProps<{
        id: string;
        label: string;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const model = defineModel<string>({ required: true });
</script>

<template>
    <div>
        <label
            :for="id"
            class="mb-1 block text-sm text-gray-700 dark:text-neutral-300"
            >{{ label }}</label
        >
        <input
            :id="id"
            v-model="model"
            type="date"
            :disabled="disabled"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:[color-scheme:dark]"
        />
    </div>
</template>
