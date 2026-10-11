<script setup lang="ts">
import {
    index as campaignIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/OfferController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreOfferFormFields from '@/components/StoreOfferFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreCampaignProduct,
    StoreOffer,
    StoreCampaignRow,
} from '@/types/store';
defineProps<{
    offer: StoreOffer;
    products: StoreCampaignRow[];
    selectedProducts: StoreCampaignProduct[];
    baseProductId: number | null;
    baseQuantity: number;
    selectedBrand: { id: number; name: string } | null;
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.offers.edit.title')"
        :action="update(offer)"
        :submit-label="t('store.offers.edit.submit')"
        :submitting-label="t('store.campaigns.saving')"
        :cancel-href="campaignIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreOfferFormFields
            :offer="offer"
            :base-product-id="baseProductId"
            :base-quantity="baseQuantity"
            :selected-brand="selectedBrand"
            :products="products"
            :selected-products="selectedProducts"
            :errors="errors"
    /></ResourceFormPage>
</template>
