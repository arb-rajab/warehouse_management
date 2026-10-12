<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';

defineProps<{
    name?: string;
    code?: string;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.colors.fields.name')"
            :value="name"
            :error="errors.name"
            maxlength="30"
            required
        />
        <FormField
            id="code"
            :label="t('store.colors.fields.code')"
            :value="code"
            :error="errors.code"
            maxlength="10"
            required
        />
    </div>
</template>
