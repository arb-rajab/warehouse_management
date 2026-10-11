<script setup lang="ts">
import {
    index as couponIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCouponFormFields from '@/components/StoreCouponFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCoupon, StoreCouponProductOption } from '@/types/store';

defineProps<{
    coupon: StoreCoupon;
    selectedProducts: StoreCouponProductOption[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.coupons.edit.title')"
        :action="update(coupon)"
        :submit-label="t('store.coupons.edit.submit')"
        :submitting-label="t('store.coupons.edit.submitting')"
        :cancel-href="couponIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreCouponFormFields
            :coupon="coupon"
            :selected-products="selectedProducts"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
