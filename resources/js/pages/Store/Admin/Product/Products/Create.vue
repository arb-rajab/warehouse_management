<script setup lang="ts">
import {
    inHouse as inHouseProducts,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreProductFormFields from '@/components/StoreProductFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreDigitalProductTax,
    StoreProductColor,
    StoreProductFormSections,
    StoreReferenceOption,
} from '@/types/store';

defineProps<{
    categories: StoreReferenceOption[];
    brands: StoreReferenceOption[];
    colors: StoreProductColor[];
    attributes: StoreReferenceOption[];
    flashDeals: StoreReferenceOption[];
    taxes: StoreDigitalProductTax[];
    sections: StoreProductFormSections;
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.products.form.createTitle')"
        :action="store()"
        :submit-label="t('store.products.form.savePublish')"
        :submitting-label="t('store.products.form.saving')"
        :cancel-href="inHouseProducts()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreProductFormFields
            :categories="categories"
            :brands="brands"
            :colors="colors"
            :attributes="attributes"
            :flash-deals="flashDeals"
            :taxes="taxes"
            :sections="sections"
            :errors="errors"
        />
        <!-- The legacy form's two buttons: this one first, as there, and the
             publishing one below. Without a button the product is published. -->
        <div class="flex justify-end">
            <button
                type="submit"
                name="button"
                value="unpublish"
                class="cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
            >
                {{ t('store.products.form.saveUnpublish') }}
            </button>
        </div>
    </ResourceFormPage>
</template>
