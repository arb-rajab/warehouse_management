<script setup lang="ts">
import {
    index as brandsIndex,
    edit,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BrandController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBrandFormFields from '@/components/StoreBrandFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreBrandForm, StoreLanguage, StoreUpload } from '@/types/store';
defineProps<{
    brand: StoreBrandForm;
    name: string;
    lang: string;
    languages: StoreLanguage[];
    initialFiles: Record<string, StoreUpload[]>;
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.brands.edit')"
        :action="update(brand)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="brandsIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit(brand, { query: { lang: code } })"
        />
        <StoreBrandFormFields
            :key="lang"
            :brand="brand"
            :name="name"
            :initial-files="initialFiles"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
