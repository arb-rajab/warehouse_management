<script setup lang="ts">
import {
    index as languagesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageFormFields from '@/components/StoreLanguageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { AdminLanguage } from '@/types/store';
defineProps<{ language: AdminLanguage }>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.languages.edit')"
        :action="update(language)"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :cancel-href="languagesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreLanguageFormFields :language="language" :errors="errors" />
    </ResourceFormPage>
</template>
