<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';

defineProps<{ name?: string; errors: Partial<Record<string, string>> }>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.attributes.fields.name')"
            :value="name"
            :error="errors.name"
            maxlength="50"
            required
        />
    </div>
</template>
