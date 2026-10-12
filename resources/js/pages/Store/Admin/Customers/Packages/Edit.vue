<script setup lang="ts">
import {
    update,
    index as resourceIndex,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerPackageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerPackageFormFields from '@/components/StoreCustomerPackageFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreCustomerPackage,
    StoreLanguage,
    StoreUpload,
} from '@/types/store';
defineProps<{
    package: StoreCustomerPackage;
    lang: string;
    languages: StoreLanguage[];
    name: string;
    initialFiles: StoreUpload[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.customerPackages.edit')"
        :action="update(package)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit(package, { query: { lang: code } })"
        />
        <StoreCustomerPackageFormFields
            :errors="errors"
            :package="package"
            :initial-files="initialFiles"
            :key="lang"
            :name="name"
        />
    </ResourceFormPage>
</template>
