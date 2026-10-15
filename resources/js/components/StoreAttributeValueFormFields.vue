<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';

defineProps<{
    value?: string;
    attributeId: number;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <input type="hidden" name="attribute_id" :value="attributeId" />
        <FormField
            id="value"
            :label="t('store.attributeValues.fields.value')"
            :value="value"
            :error="errors.value"
            maxlength="255"
            required
        />
        <FieldError :message="errors.attribute_id" />
    </div>
</template>
