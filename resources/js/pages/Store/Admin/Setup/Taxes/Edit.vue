<script setup lang="ts">
import {
    update,
    index as resourceIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTaxFormFields from '@/components/StoreTaxFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreTax } from '@/types/store';
defineProps<{ tax: StoreTax }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.taxes.edit')"
        :action="update(tax)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreTaxFormFields :errors="errors" :tax="tax" />
    </ResourceFormPage>
</template>
