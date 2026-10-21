<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreReferenceOption } from '@/types/store';
defineProps<{
    id: string;
    label: string;
    options: StoreReferenceOption[];
    value?: string | number | null;
    error?: string;
    required?: boolean;
}>();
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabelClass"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <select
            :id="id"
            :name="id"
            :required="required"
            :class="[plainFieldInputClass, 'cursor-pointer']"
        >
            <option value="" :selected="value == null || value === ''">
                {{ t('store.reference.select') }}
            </option>
            <option
                v-for="option in options"
                :key="option.id"
                :value="option.id"
                :selected="String(value) === String(option.id)"
            >
                {{ option.name }}
            </option>
        </select>
        <FieldError :message="error" />
    </div>
</template>
