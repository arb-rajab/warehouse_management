<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { discountRangePattern, productAmountMax } from '@/lib/storeProductForm';

/**
 * A product's discount, its type and its optional period, posted as
 * `discount`, `discount_type` and `date_range` (`d-m-Y H:i:s to d-m-Y H:i:s`).
 */
const props = defineProps<{
    discount?: string | null;
    discountType?: string | null;
    dateRange?: string | null;
    errors: Record<string, string>;
}>();
const type = ref(props.discountType === 'percent' ? 'percent' : 'amount');
</script>

<template>
    <div class="contents">
        <div class="lg:col-span-2">
            <FormField
                id="date_range"
                :label="t('store.productForm.dateRange')"
                :value="dateRange ?? ''"
                :error="errors.date_range"
                :placeholder="t('store.productForm.dateFormat')"
                maxlength="42"
                :pattern="discountRangePattern"
                dir="ltr"
                aria-describedby="date_range_help"
            />
            <p
                id="date_range_help"
                class="mt-1 text-xs text-gray-600 dark:text-neutral-400"
            >
                {{ t('store.productForm.dateHelp') }}
            </p>
        </div>
        <FormField
            id="discount"
            type="number"
            :label="t('store.productForm.discount')"
            :value="discount ?? '0'"
            :error="errors.discount"
            min="0"
            :max="productAmountMax"
            step="0.01"
            required
        />
        <div>
            <label for="discount_type" :class="fieldLabelClass">{{
                t('store.productForm.discountType')
            }}</label>
            <select
                id="discount_type"
                v-model="type"
                name="discount_type"
                :class="plainFieldInputClass"
                required
            >
                <option value="amount">
                    {{ t('store.productForm.flat') }}
                </option>
                <option value="percent">
                    {{ t('store.productForm.percent') }}
                </option>
            </select>
            <FieldError :message="errors.discount_type" />
        </div>
    </div>
</template>
