<script setup lang="ts">
import {
    update,
    index as resourceIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CarrierController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCarrierFormFields from '@/components/StoreCarrierFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreCarrier,
    StoreUpload,
    StoreReferenceOption,
} from '@/types/store';
defineProps<{
    carrier: StoreCarrier;
    initialFiles: StoreUpload[];
    zones: StoreReferenceOption[];
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.carriers.edit')"
        :action="update(carrier)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreCarrierFormFields
            :errors="errors"
            :zones="zones"
            :carrier="carrier"
            :initial-files="initialFiles"
        />
    </ResourceFormPage>
</template>
