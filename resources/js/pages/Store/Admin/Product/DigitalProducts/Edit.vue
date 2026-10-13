<script setup lang="ts">
import {
    index as digitalProductsIndex,
    edit,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/DigitalProductController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDigitalProductFormFields from '@/components/StoreDigitalProductFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreDigitalProductForm,
    StoreDigitalProductTax,
    StoreLanguage,
    StoreReferenceOption,
    StoreUpload,
} from '@/types/store';

defineProps<{
    product: StoreDigitalProductForm;
    name: string;
    description: string;
    lang: string;
    languages: StoreLanguage[];
    categories: StoreReferenceOption[];
    taxes: StoreDigitalProductTax[];
    initialFiles: Record<string, StoreUpload[]>;
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.digitalProducts.form.editTitle')"
        :action="update(product)"
        :submit-label="t('store.digitalProducts.form.update')"
        :submitting-label="t('store.digitalProducts.form.updating')"
        :cancel-href="digitalProductsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit(product, { query: { lang: code } })"
        />
        <StoreDigitalProductFormFields
            :key="lang"
            :product="product"
            :name="name"
            :description="description"
            :categories="categories"
            :taxes="taxes"
            :initial-files="initialFiles"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
