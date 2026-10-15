<script setup lang="ts">
import {
    edit,
    editVariantCombinations,
    inHouse as inHouseProducts,
    sellerEdit,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreProductFormFields from '@/components/StoreProductFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreDigitalProductTax,
    StoreLanguage,
    StoreProductColor,
    StoreProductEditListType,
    StoreProductForm,
    StoreProductFormSections,
    StoreProductVariantState,
    StoreReferenceOption,
    StoreUpload,
} from '@/types/store';

/**
 * The physical product edit form, opened from the in-house or all-products
 * list (`products.admin.edit`) or the Blade seller list
 * (`products.seller.edit`); its language tabs stay on the route it was
 * opened from. Only the in-house list is an Inertia page to cancel back to.
 */
const props = defineProps<{
    product: StoreProductForm;
    name: string;
    unit: string;
    description: string;
    lang: string;
    languages: StoreLanguage[];
    listType: StoreProductEditListType;
    categories: StoreReferenceOption[];
    brands: StoreReferenceOption[];
    colors: StoreProductColor[];
    attributes: StoreReferenceOption[];
    flashDeals: StoreReferenceOption[];
    taxes: StoreDigitalProductTax[];
    sections: StoreProductFormSections;
    variants: StoreProductVariantState;
    initialFiles: Record<string, StoreUpload[]>;
}>();
const editRoute = props.listType === 'seller' ? sellerEdit : edit;
</script>
<template>
    <ResourceFormPage
        :title="t('store.products.form.editTitle')"
        :action="update(product)"
        :submit-label="t('store.products.form.update')"
        :submitting-label="t('store.products.form.updating')"
        :cancel-href="listType === 'admin' ? inHouseProducts() : undefined"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => editRoute(product, { query: { lang: code } })"
        />
        <StoreProductFormFields
            :key="lang"
            :categories="categories"
            :brands="brands"
            :colors="colors"
            :attributes="attributes"
            :flash-deals="flashDeals"
            :taxes="taxes"
            :sections="sections"
            :product="product"
            :name="name"
            :unit="unit"
            :description="description"
            :variants="variants"
            :variants-url="editVariantCombinations.url(product)"
            :initial-files="initialFiles"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
