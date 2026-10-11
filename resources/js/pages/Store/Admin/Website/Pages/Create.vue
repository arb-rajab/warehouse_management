<script setup lang="ts">
import { ref } from 'vue';
import {
    store,
    index as pagesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import FieldError from '@/components/FieldError.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsitePageFormFields from '@/components/StoreWebsitePageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreWebsitePageForm } from '@/types/store';
const props = defineProps<{
    lang: string;
    languages: { code: string; name: string }[];
}>();
const page = ref<StoreWebsitePageForm>({
    title: '',
    slug: '',
    content: '',
    meta_title: '',
    meta_description: '',
    keywords: '',
    meta_image: '',
});
function transform() {
    return {
        title: page.value.title,
        slug: page.value.slug,
        content: page.value.content,
        meta_title: page.value.meta_title,
        meta_description: page.value.meta_description,
        keywords: page.value.keywords,
        meta_image: page.value.meta_image,
        lang: props.lang,
    };
}
</script>
<template>
    <ResourceFormPage
        :title="t('store.website.pages.add')"
        :action="store()"
        :cancel-href="pagesIndex()"
        :submit-label="t('store.website.save')"
        :submitting-label="t('store.website.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
    >
        <template #default="{ errors }"
            ><StoreWebsitePageFormFields
                v-model="page"
                :errors="errors" /><FieldError
                :message="errors.lang" /></template
    ></ResourceFormPage>
</template>
