<script setup lang="ts">
import {
    index as categoriesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCategoryFormFields from '@/components/StoreCategoryFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCategoryOptions } from '@/types/store';
defineProps<StoreCategoryOptions>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.categories.create')"
        :action="store()"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="categoriesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreCategoryFormFields
            :parents="parents"
            :attributes="attributes"
            :commission-enabled="commissionEnabled"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
