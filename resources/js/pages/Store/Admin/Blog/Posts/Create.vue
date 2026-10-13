<script setup lang="ts">
import {
    index as blogsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogFormFields from '@/components/StoreBlogFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreBlogCategory } from '@/types/store';

defineProps<{ categories: StoreBlogCategory[] }>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.blogPosts.create.title')"
        :action="store()"
        :submit-label="t('store.blogPosts.create.submit')"
        :submitting-label="t('store.blogPosts.create.submitting')"
        :cancel-href="blogsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreBlogFormFields :categories="categories" :errors="errors" />
    </ResourceFormPage>
</template>
