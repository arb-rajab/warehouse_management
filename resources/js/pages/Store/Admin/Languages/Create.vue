<script setup lang="ts">
import {
    index as languagesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageFormFields from '@/components/StoreLanguageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>
<template>
    <ResourceFormPage
        :title="t('store.languages.create')"
        :action="store()"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :cancel-href="languagesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreLanguageFormFields :errors="errors" />
    </ResourceFormPage>
</template>
