<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
defineProps<{ id: string; label: string; value?: boolean; error?: string }>();
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <select
            :id="id"
            :name="id"
            required
            :class="[plainFieldInputClass, 'cursor-pointer']"
        >
            <option value="1" :selected="value === true">
                {{ t('store.reference.enabled') }}
            </option>
            <option value="0" :selected="value !== true">
                {{ t('store.reference.disabled') }}
            </option>
        </select>
        <FieldError :message="error" />
    </div>
</template>
