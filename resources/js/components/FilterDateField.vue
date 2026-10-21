<script setup lang="ts">
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass } from '@/lib/filters';

withDefaults(
    defineProps<{
        id: string;
        label: string;
        disabled?: boolean;
        min?: string;
        required?: boolean;
    }>(),
    {
        disabled: false,
        required: false,
    },
);

const model = defineModel<string>({ required: true });
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabelClass"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <input
            :id="id"
            v-model="model"
            type="date"
            :disabled="disabled"
            :min="min"
            :required="required"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:[color-scheme:dark]"
        />
    </div>
</template>
