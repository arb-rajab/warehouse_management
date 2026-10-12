<script setup lang="ts">
import { all_orders } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    store,
    shippingStore,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreOrderEntryFields from '@/components/StoreOrderEntryFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
defineProps<{ representativeOrder: boolean; submissionKey: string }>();
</script>
<template>
    <ResourceFormPage
        :title="
            t(
                representativeOrder
                    ? 'store.orders.createRepresentative'
                    : 'store.orders.createShipping',
            )
        "
        :action="representativeOrder ? store() : shippingStore()"
        :layout="StoreAdminLayout"
        :cancel-href="all_orders['/temp/admin/all_orders']()"
        wide
        :submit-label="t('store.orders.save')"
        :submitting-label="t('store.orders.saving')"
        #default="{ errors }"
    >
        <StoreOrderEntryFields
            :representative-order="representativeOrder"
            :submission-key="submissionKey"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
