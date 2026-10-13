<script setup lang="ts">
import {
    index as couponIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCouponFormFields from '@/components/StoreCouponFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCouponProductOption } from '@/types/store';

defineProps<{ selectedProducts: StoreCouponProductOption[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.coupons.create.title')"
        :action="store()"
        :submit-label="t('store.coupons.create.submit')"
        :submitting-label="t('store.coupons.create.submitting')"
        :cancel-href="couponIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreCouponFormFields
            :selected-products="selectedProducts"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
