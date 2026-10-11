<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';

defineProps<{ brandId: number; errors: Record<string, string> }>();
</script>
<template>
    <div class="space-y-3">
        <input type="hidden" name="brand_id" :value="brandId" />
        <input type="hidden" name="seller_product_discount" value="0" />
        <FormField
            :id="`discount_${brandId}`"
            name="discount"
            type="number"
            value="0"
            min="0"
            step="any"
            required
            :label="t('store.brandDiscount.discount')"
            :error="errors.discount"
        />
        <FormField
            :id="`date_range_${brandId}`"
            name="date_range"
            maxlength="42"
            pattern="\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2} to \d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}"
            :placeholder="t('store.brandDiscount.dateFormat')"
            :label="t('store.brandDiscount.dateRange')"
            :error="errors.date_range"
        />
        <p>{{ t('store.brandDiscount.dateHelp') }}</p>
    </div>
</template>
