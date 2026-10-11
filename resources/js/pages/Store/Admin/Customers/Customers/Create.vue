<script setup lang="ts">
import {
    store,
    repStore,
    index as customersIndex,
    repIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerFormFields from '@/components/StoreCustomerFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { AddressOption } from '@/types/storeCustomers';
defineProps<{ representative: boolean; countries: AddressOption[] }>();
</script>
<template>
    <ResourceFormPage
        :title="
            t(
                representative
                    ? 'store.customers.createRepresentative'
                    : 'store.customers.create',
            )
        "
        :action="representative ? repStore() : store()"
        :cancel-href="representative ? repIndex() : customersIndex()"
        :submit-label="t('store.customers.save')"
        :submitting-label="t('store.customers.saving')"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreCustomerFormFields
            :countries="countries"
            :representative="representative"
            :errors="errors"
            creating
        />
    </ResourceFormPage>
</template>
