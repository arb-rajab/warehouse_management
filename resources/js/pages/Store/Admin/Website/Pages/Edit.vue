<script setup lang="ts">
import {
    edit,
    index as pagesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreWebsitePageFormFields from '@/components/StoreWebsitePageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreLanguage,
    StoreUpload,
    StoreWebsitePageForm,
} from '@/types/store';

defineProps<{
    page: StoreWebsitePageForm;
    title: string;
    content: string;
    lang: string;
    languages: StoreLanguage[];
    homeUrl: string;
    initialFiles: Record<'meta_image', StoreUpload[]>;
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.website.pages.editTitle')"
        :action="update(page)"
        :submit-label="t('store.website.pages.update')"
        :submitting-label="t('store.website.saving')"
        :cancel-href="pagesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit(page, { query: { lang: code } })"
        />
        <StoreWebsitePageFormFields
            :page="page"
            :title="title"
            :content="content"
            :home-url="homeUrl"
            :initial-files="initialFiles"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
