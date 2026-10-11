<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import type { StoreCurrency } from '@/types/store';
defineProps<{
    currency?: Partial<StoreCurrency>;
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.reference.name')"
            :value="currency?.name"
            :error="errors.name"
            required
            maxlength="255"
        />
        <FormField
            id="symbol"
            :label="t('store.currencies.symbol')"
            :value="currency?.symbol"
            :error="errors.symbol"
            required
            maxlength="255"
        />
        <FormField
            id="code"
            :label="t('store.currencies.code')"
            :value="currency?.code"
            :error="errors.code"
            required
            maxlength="20"
        />
        <FormField
            id="exchange_rate"
            :label="t('store.currencies.rate')"
            type="number"
            :value="currency?.exchange_rate"
            :error="errors.exchange_rate"
            required
            min="0.00001"
            max="99999.99999"
            step="0.00001"
        />
    </div>
</template>
