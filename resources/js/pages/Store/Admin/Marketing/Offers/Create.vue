<script setup lang="ts">
import {
    index as campaignIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/OfferController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreOfferFormFields from '@/components/StoreOfferFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCampaignProduct, StoreOffer } from '@/types/store';
defineProps<{
    offerType: StoreOffer['offer_type'];
    selectedProducts: StoreCampaignProduct[];
    selectedBrand: { id: number; name: string } | null;
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.offers.create.title')"
        :action="store()"
        :submit-label="t('store.offers.create.submit')"
        :submitting-label="t('store.campaigns.saving')"
        :cancel-href="campaignIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreOfferFormFields
            :offer-type="offerType"
            :selected-brand="selectedBrand"
            :selected-products="selectedProducts"
            :errors="errors"
    /></ResourceFormPage>
</template>
