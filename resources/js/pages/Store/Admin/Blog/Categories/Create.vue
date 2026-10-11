<script setup lang="ts">
import {
    index as categoriesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogCategoryController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogCategoryFormFields from '@/components/StoreBlogCategoryFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <ResourceFormPage
        :title="t('store.blogCategories.create.title')"
        :action="store()"
        :submit-label="t('store.blogCategories.create.submit')"
        :submitting-label="t('store.blogCategories.create.submitting')"
        :cancel-href="categoriesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreBlogCategoryFormFields :errors="errors" />
    </ResourceFormPage>
</template>
