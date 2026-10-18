<script setup lang="ts">
import {
    index as sellersIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreSellerFormFields from '@/components/StoreSellerFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ seller: { id: number; name: string; email: string } }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.sellers.edit')"
        :action="update(seller)"
        :submit-label="t('store.sellers.save')"
        :submitting-label="t('store.sellers.saving')"
        :cancel-href="sellersIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreSellerFormFields
            :name="seller.name"
            :email="seller.email"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
