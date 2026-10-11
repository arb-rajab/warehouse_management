<script setup lang="ts">
import {
    index as taxesIndex,
    update,
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
        :title="t('store.taxes.edit.title', { name: tax.name })"
        :action="update(tax)"
        :submit-label="t('common.save')"
        :submitting-label="t('common.saving')"
        :cancel-href="taxesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreTaxFormFields :tax="tax" :errors="errors" />
    </ResourceFormPage>
</template>
