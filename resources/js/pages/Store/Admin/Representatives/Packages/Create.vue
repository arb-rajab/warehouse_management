<script setup lang="ts">
import {
    index as packagesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreRepresentativePackageFormFields from '@/components/StoreRepresentativePackageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <ResourceFormPage
        :title="t('store.representativePackages.create')"
        :action="store()"
        :submit-label="t('store.representativePackages.save')"
        :submitting-label="t('store.representativePackages.saving')"
        :cancel-href="packagesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreRepresentativePackageFormFields :errors="errors" />
    </ResourceFormPage>
</template>
