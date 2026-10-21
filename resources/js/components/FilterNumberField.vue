<script setup lang="ts">
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass } from '@/lib/filters';
import { useRequiredField } from '@/lib/requiredFields';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        name?: string;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
        required: false,
    },
);

useRequiredField(() => props.required);

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
            :name="name"
            :required="required"
            type="number"
            min="1"
            step="1"
            :placeholder="placeholder"
            :disabled="disabled"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800"
        />
    </div>
</template>
