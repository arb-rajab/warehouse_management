<script setup lang="ts">
import {
    index as sellersIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreSellerFormFields from '@/components/StoreSellerFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <ResourceFormPage
        :title="t('store.sellers.add')"
        :action="store()"
        :submit-label="t('store.sellers.save')"
        :submitting-label="t('store.sellers.saving')"
        :cancel-href="sellersIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreSellerFormFields :errors="errors" password-required />
    </ResourceFormPage>
</template>
