<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    index as campaignIndex,
    update,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/FlashDealController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreFlashDealFormFields from '@/components/StoreFlashDealFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreCampaignProduct,
    StoreFlashDeal,
    StoreCampaignRow,
    StoreLanguage,
} from '@/types/store';
defineProps<{
    deal: StoreFlashDeal;
    products: StoreCampaignRow[];
    selectedProducts: StoreCampaignProduct[];
    title: string;
    lang: string;
    languages: StoreLanguage[];
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.flashDeals.edit.title')"
        :action="update(deal)"
        :submit-label="t('store.flashDeals.edit.submit')"
        :submitting-label="t('store.campaigns.saving')"
        :cancel-href="campaignIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
        ><nav
            :aria-label="t('store.campaigns.fields.languages')"
            class="flex flex-wrap gap-2"
        >
            <Link
                v-for="language in languages"
                :key="language.code"
                :href="edit(deal, { query: { lang: language.code } })"
                :aria-current="language.code === lang ? 'page' : undefined"
                :class="filterTriggerButtonClass"
                >{{ language.name }}</Link
            >
        </nav>
        <input type="hidden" name="lang" :value="lang" />
        <StoreFlashDealFormFields
            :key="lang"
            :deal="deal"
            :title="title"
            :products="products"
            :selected-products="selectedProducts"
            :errors="errors"
    /></ResourceFormPage>
</template>
