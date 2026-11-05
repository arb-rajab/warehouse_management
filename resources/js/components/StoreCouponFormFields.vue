<script setup lang="ts">
import { ref } from 'vue';
import { productOptions } from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';
import type { StoreCampaignProduct, StoreCoupon } from '@/types/store';

const props = defineProps<{
    coupon?: StoreCoupon;
    selectedProducts: StoreCampaignProduct[];
    errors: Record<string, string>;
}>();

const type = ref(props.coupon?.type ?? 'cart_base');
const discountType = ref(props.coupon?.discount_type ?? 'amount');
const startDate = ref(props.coupon?.start_date ?? '');
const endDate = ref(props.coupon?.end_date ?? '');
const products = ref<SearchOption[]>([...props.selectedProducts]);
</script>

<template>
    <div class="space-y-4">
        <div>
            <label for="type" :class="fieldLabelClass"
                >{{ t('store.coupons.fields.type') }}<RequiredMark
            /></label>
            <select
                id="type"
                v-model="type"
                name="type"
                required
                :class="plainFieldInputClass"
            >
                <option value="cart_base">
                    {{ t('store.coupons.fields.cart') }}
                </option>
                <option value="product_base">
                    {{ t('store.coupons.fields.product') }}
                </option>
            </select>
            <FieldError :message="errors.type" />
        </div>
        <FormField
            id="code"
            :label="t('store.coupons.fields.code')"
            :value="coupon?.code ?? ''"
            :error="errors.code"
            maxlength="255"
            required
        />
        <div>
            <label for="discount_type" :class="fieldLabelClass"
                >{{ t('store.coupons.fields.discountType') }}<RequiredMark
            /></label>
            <select
                id="discount_type"
                v-model="discountType"
                name="discount_type"
                required
                :class="plainFieldInputClass"
            >
                <option value="amount">
                    {{ t('store.coupons.fields.amount') }}
                </option>
                <option value="percent">
                    {{ t('store.coupons.fields.percent') }}
                </option>
            </select>
            <FieldError :message="errors.discount_type" />
        </div>
        <FormField
            id="discount"
            :label="t('store.coupons.fields.discount')"
            type="number"
            :value="coupon?.discount ?? ''"
            :error="errors.discount"
            min="1"
            :max="discountType === 'percent' ? 100 : undefined"
            step="0.01"
            required
        />
        <FormField
            id="start_date"
            :label="t('store.coupons.fields.startDate')"
            type="date"
            input-class="dark:[color-scheme:dark]"
            :value="startDate"
            :error="errors.start_date"
            :max="endDate || undefined"
            required
            @input="startDate = ($event.target as HTMLInputElement).value"
        />
        <FormField
            id="end_date"
            :label="t('store.coupons.fields.endDate')"
            type="date"
            input-class="dark:[color-scheme:dark]"
            :value="endDate"
            :error="errors.end_date"
            :min="startDate || undefined"
            required
            @input="endDate = ($event.target as HTMLInputElement).value"
        />
        <template v-if="type === 'cart_base'">
            <FormField
                id="min_buy"
                :label="t('store.coupons.fields.minBuy')"
                type="number"
                :value="coupon?.min_buy ?? ''"
                :error="errors.min_buy"
                min="1"
                step="0.01"
                required
            />
            <FormField
                id="max_discount"
                :label="t('store.coupons.fields.maxDiscount')"
                type="number"
                :value="coupon?.max_discount ?? ''"
                :error="errors.max_discount"
                min="1"
                step="0.01"
                required
            />
        </template>
        <div v-else class="space-y-2">
            <StoreSearchSelect
                id="product_ids"
                v-model:items="products"
                name="product_ids[]"
                multiple
                :label="t('store.coupons.fields.products')"
                :endpoint="productOptions"
                :placeholder="t('store.coupons.fields.chooseProducts')"
                :search-placeholder="t('store.coupons.fields.searchProducts')"
                required
                :error="errors.product_ids"
            />
            <FieldError
                v-for="(message, key) in errors"
                :key="key"
                :message="key.startsWith('product_ids.') ? message : undefined"
            />
        </div>
    </div>
</template>
