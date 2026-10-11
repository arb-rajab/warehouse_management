<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import type { StoreCurrency } from '@/types/store';

defineProps<{
    currency?: StoreCurrency;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.currencies.fields.name')"
            :value="currency?.name"
            :error="errors.name"
            maxlength="255"
            required
        />
        <FormField
            id="symbol"
            :label="t('store.currencies.fields.symbol')"
            :value="currency?.symbol"
            :error="errors.symbol"
            maxlength="255"
            required
        />
        <FormField
            id="code"
            :label="t('store.currencies.fields.code')"
            :value="currency?.code ?? ''"
            :error="errors.code"
            maxlength="20"
            required
        />
        <FormField
            id="exchange_rate"
            :label="t('store.currencies.fields.exchangeRate')"
            type="number"
            :value="currency?.exchange_rate"
            :error="errors.exchange_rate"
            min="0"
            max="99999.99999"
            step="0.00001"
            required
        />
    </div>
</template>
