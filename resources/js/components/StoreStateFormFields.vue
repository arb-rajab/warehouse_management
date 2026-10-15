<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import { t } from '@/lib/i18n';
import type { StoreState, StoreReferenceOption } from '@/types/store';
defineProps<{
    state?: StoreState;
    countries: StoreReferenceOption[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.reference.name')"
            :value="state?.name"
            :error="errors.name"
            required
            maxlength="255"
        />
        <StoreSelectField
            id="country_id"
            :label="t('store.reference.country')"
            :options="countries"
            :value="state?.country_id"
            :error="errors.country_id"
            required
        />
    </div>
</template>
