<script setup lang="ts">
import { all_orders } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreOrderEntryFields from '@/components/StoreOrderEntryFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreOrder } from '@/types/store';
defineProps<{
    order: StoreOrder;
    additionalInfo: string | null;
    couponDiscount: number;
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.orders.edit')"
        :action="update(order)"
        :layout="StoreAdminLayout"
        :cancel-href="all_orders['/temp/admin/all_orders']()"
        wide
        :submit-label="t('store.orders.save')"
        :submitting-label="t('store.orders.saving')"
        #default="{ errors }"
    >
        <StoreOrderEntryFields
            :representative-order="false"
            :order="order"
            :additional-info="additionalInfo"
            :coupon-discount="couponDiscount"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
