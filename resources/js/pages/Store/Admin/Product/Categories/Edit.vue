<script setup lang="ts">
import {
    index as categoriesIndex,
    edit,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCategoryFormFields from '@/components/StoreCategoryFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreCategoryForm,
    StoreCategoryOptions,
    StoreLanguage,
    StoreUpload,
} from '@/types/store';
defineProps<
    StoreCategoryOptions & {
        category: StoreCategoryForm;
        name: string;
        lang: string;
        languages: StoreLanguage[];
        initialFiles: Record<string, StoreUpload[]>;
    }
>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.categories.edit')"
        :action="update(category)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="categoriesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit(category, { query: { lang: code } })"
        />
        <StoreCategoryFormFields
            :key="lang"
            :category="category"
            :name="name"
            :parents="parents"
            :attributes="attributes"
            :commission-enabled="commissionEnabled"
            :initial-files="initialFiles"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
