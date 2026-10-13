<script setup lang="ts">
import {
    index as digitalProductsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/DigitalProductController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDigitalProductFormFields from '@/components/StoreDigitalProductFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreDigitalProductTax,
    StoreReferenceOption,
} from '@/types/store';

defineProps<{
    categories: StoreReferenceOption[];
    taxes: StoreDigitalProductTax[];
}>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.digitalProducts.form.createTitle')"
        :action="store()"
        :submit-label="t('store.digitalProducts.form.save')"
        :submitting-label="t('store.digitalProducts.form.saving')"
        :cancel-href="digitalProductsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreDigitalProductFormFields
            :categories="categories"
            :taxes="taxes"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
