<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import { t } from '@/lib/i18n';
import type { StoreCity, StoreReferenceOption } from '@/types/store';
defineProps<{
    city?: StoreCity;
    name?: string;
    states: StoreReferenceOption[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.reference.name')"
            :value="name ?? city?.name"
            :error="errors.name"
            required
            maxlength="255"
        />
        <StoreSelectField
            id="state_id"
            :label="t('store.reference.state')"
            :options="states"
            :value="city?.state_id"
            :error="errors.state_id"
            required
        />
        <FormField
            id="cost"
            :label="t('store.cities.cost')"
            type="number"
            :value="city?.cost ?? '0'"
            :error="errors.cost"
            required
            min="0"
            max="999999999999999999.99"
            step="0.01"
        />
    </div>
</template>
