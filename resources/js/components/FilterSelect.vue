<script setup lang="ts">
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';

defineProps<{
    id: string;
    label: string;
    allLabel: string;
    options: { value: string | number; label: string }[];
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <select :id="id" v-model="model" :class="plainFieldInputClass">
            <option value="">{{ allLabel }}</option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
    </div>
</template>
