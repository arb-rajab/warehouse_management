<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import type { StoreTax } from '@/types/store';
defineProps<{ tax?: StoreTax; errors: Record<string, string> }>();
</script>

<template>
    <div>
        <FormField
            id="name"
            :label="t('store.reference.name')"
            :value="tax?.name"
            :error="errors.name"
            required
            maxlength="255"
        />
    </div>
</template>
