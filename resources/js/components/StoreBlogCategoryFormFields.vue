<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';

defineProps<{
    categoryName?: string;
    errors: Partial<Record<'category_name', string>>;
}>();
</script>

<template>
    <FormField
        id="category_name"
        :label="t('store.blogCategories.fields.name')"
        :value="categoryName ?? ''"
        :error="errors.category_name"
        required
        maxlength="255"
    />
</template>
