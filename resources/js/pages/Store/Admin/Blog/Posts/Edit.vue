<script setup lang="ts">
import {
    index as blogsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogFormFields from '@/components/StoreBlogFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreBlog, StoreBlogCategory, StoreUpload } from '@/types/store';

defineProps<{
    blog: StoreBlog;
    categories: StoreBlogCategory[];
    initialFiles: Record<string, StoreUpload[]>;
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.blogPosts.edit.title')"
        :action="update(blog)"
        :submit-label="t('store.blogPosts.edit.submit')"
        :submitting-label="t('store.blogPosts.edit.submitting')"
        :cancel-href="blogsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreBlogFormFields
            :blog="blog"
            :categories="categories"
            :initial-files="initialFiles"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
