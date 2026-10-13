<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import type { RepresentativePackage } from '@/types/storeRepresentatives';

defineProps<{
    package?: RepresentativePackage;
    errors: Partial<Record<'name' | 'percentage', string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.representativePackages.name')"
            :value="package?.name ?? ''"
            :error="errors.name"
            required
            maxlength="50"
        />
        <FormField
            id="percentage"
            :label="t('store.representativePackages.percentage')"
            type="number"
            :value="package?.percentage ?? ''"
            :error="errors.percentage"
            required
            min="0"
            max="100"
            step="any"
        />
    </div>
</template>
