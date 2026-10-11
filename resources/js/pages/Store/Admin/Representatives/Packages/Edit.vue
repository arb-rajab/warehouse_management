<script setup lang="ts">
import {
    index as packagesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreRepresentativePackageFormFields from '@/components/StoreRepresentativePackageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { RepresentativePackage } from '@/types/storeRepresentatives';

defineProps<{ package: RepresentativePackage }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.representativePackages.edit')"
        :action="update(package)"
        :submit-label="t('store.representativePackages.save')"
        :submitting-label="t('store.representativePackages.saving')"
        :cancel-href="packagesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreRepresentativePackageFormFields
            :package="package"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
