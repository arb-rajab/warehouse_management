<script setup lang="ts">
import {
    index as categoriesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogCategoryController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogCategoryFormFields from '@/components/StoreBlogCategoryFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreBlogCategory } from '@/types/store';
defineProps<{ category: StoreBlogCategory }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.blogCategories.edit.title')"
        :action="update(category)"
        :submit-label="t('store.blogCategories.edit.submit')"
        :submitting-label="t('store.blogCategories.edit.submitting')"
        :cancel-href="categoriesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreBlogCategoryFormFields
            :category-name="category.category_name"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
