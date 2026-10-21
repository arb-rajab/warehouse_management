<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { productAmountMax } from '@/lib/storeProductForm';
import type { StoreDigitalProductTax } from '@/types/store';

/**
 * The active taxes a product form lists, each with its amount and type,
 * posted as `tax_id[]`, `tax[]` and `tax_type[]` for ProductTaxService.
 */
const props = defineProps<{
    taxes: StoreDigitalProductTax[];
    errors: Record<string, string>;
}>();
const taxTypes = ref(props.taxes.map((tax) => tax.tax_type));
</script>

<template>
    <div class="contents">
        <div
            v-for="(tax, index) in taxes"
            :key="tax.id"
            class="grid grid-cols-[1fr_auto] items-start gap-2"
        >
            <input type="hidden" name="tax_id[]" :value="tax.id" />
            <div>
                <label :for="`tax_${tax.id}`" :class="fieldLabelClass"
                    >{{ tax.name }}<RequiredMark
                /></label>
                <input
                    :id="`tax_${tax.id}`"
                    name="tax[]"
                    type="number"
                    :value.attr="tax.tax"
                    :class="plainFieldInputClass"
                    min="0"
                    :max="productAmountMax"
                    step="0.01"
                    required
                />
                <FieldError :message="errors[`tax.${index}`]" />
            </div>
            <div>
                <label :for="`tax_type_${tax.id}`" :class="fieldLabelClass"
                    >{{ t('store.productForm.taxType', { tax: tax.name })
                    }}<RequiredMark
                /></label>
                <select
                    :id="`tax_type_${tax.id}`"
                    v-model="taxTypes[index]"
                    name="tax_type[]"
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
                <FieldError :message="errors[`tax_type.${index}`]" />
            </div>
        </div>
        <FieldError :message="errors.tax_id ?? errors.tax ?? errors.tax_type" />
    </div>
</template>
