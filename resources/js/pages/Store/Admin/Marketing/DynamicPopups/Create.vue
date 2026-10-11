<script setup lang="ts">
import {
    index as popupsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/DynamicPopupController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDynamicPopupFormFields from '@/components/StoreDynamicPopupFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreMarketingOption } from '@/types/store';

defineProps<{
    offers: StoreMarketingOption[];
    categories: StoreMarketingOption[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.dynamicPopups.create.title')"
        :action="store()"
        :submit-label="t('store.dynamicPopups.create.submit')"
        :submitting-label="t('store.dynamicPopups.create.submitting')"
        :cancel-href="popupsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreDynamicPopupFormFields
            :offers="offers"
            :categories="categories"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
