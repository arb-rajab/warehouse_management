<script setup lang="ts">
import {
    index as packagesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerPackageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerPackageFormFields from '@/components/StoreCustomerPackageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <ResourceFormPage
        :title="t('store.customerPackages.create')"
        :action="store()"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="packagesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreCustomerPackageFormFields :errors="errors" />
    </ResourceFormPage>
</template>
