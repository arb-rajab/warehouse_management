<script setup lang="ts">
import {
    index as campaignIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/FlashDealController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreFlashDealFormFields from '@/components/StoreFlashDealFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCampaignProduct } from '@/types/store';
defineProps<{ selectedProducts: StoreCampaignProduct[] }>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.flashDeals.create.title')"
        :action="store()"
        :submit-label="t('store.flashDeals.create.submit')"
        :submitting-label="t('store.campaigns.saving')"
        :cancel-href="campaignIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreFlashDealFormFields
            :selected-products="selectedProducts"
            :errors="errors"
    /></ResourceFormPage>
</template>
