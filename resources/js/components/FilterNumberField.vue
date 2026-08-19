<script setup lang="ts">
import { fieldLabelClass } from '@/lib/filters';

withDefaults(
    defineProps<{
        id: string;
        label: string;
        placeholder?: string;
        disabled?: boolean;
    }>(),
    {
        placeholder: undefined,
        disabled: false,
    },
);

const model = defineModel<string>({ required: true });
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            type="number"
            min="1"
            step="1"
            :placeholder="placeholder"
            :disabled="disabled"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800"
        />
    </div>
</template>
