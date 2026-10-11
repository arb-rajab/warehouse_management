<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import type { StoreTax } from '@/types/store';

defineProps<{
    tax?: StoreTax;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <FormField
        id="name"
        :label="t('store.taxes.fields.name')"
        :value="tax?.name"
        :error="errors.name"
        maxlength="255"
        required
    />
</template>
