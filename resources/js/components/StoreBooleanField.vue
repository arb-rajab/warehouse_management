<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
defineProps<{
    name: string;
    label: string;
    value: boolean;
    error?: string;
    disabled?: boolean;
}>();
</script>
<template>
    <div>
        <label :for="name" :class="fieldLabelClass">{{ label }}</label>
        <select
            :id="name"
            :name="name"
            required
            :disabled="disabled"
            :class="[
                plainFieldInputClass,
                'cursor-pointer disabled:cursor-not-allowed',
            ]"
        >
            <option value="1" :selected="value">
                {{ t('store.settings.enabled') }}
            </option>
            <option value="0" :selected="!value">
                {{ t('store.settings.disabled') }}
            </option>
        </select>
        <FieldError :message="error" />
    </div>
</template>
