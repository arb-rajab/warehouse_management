<script setup lang="ts">
import {
    index as popupsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/DynamicPopupController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDynamicPopupFormFields from '@/components/StoreDynamicPopupFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreDynamicPopup, StoreMarketingOption } from '@/types/store';

defineProps<{
    popup: StoreDynamicPopup;
    offers: StoreMarketingOption[];
    categories: StoreMarketingOption[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.dynamicPopups.edit.title')"
        :action="update(popup)"
        :submit-label="t('store.dynamicPopups.edit.submit')"
        :submitting-label="t('store.dynamicPopups.edit.submitting')"
        :cancel-href="popupsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreDynamicPopupFormFields
            :popup="popup"
            :offers="offers"
            :categories="categories"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
