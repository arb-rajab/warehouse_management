<script setup lang="ts">
import {
    update,
    index as resourceIndex,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/PickupPointController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StorePickupPointFormFields from '@/components/StorePickupPointFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StorePickupPoint,
    StoreReferenceOption,
    StoreLanguage,
} from '@/types/store';
defineProps<{
    pickupPoint: StorePickupPoint;
    staffOptions: StoreReferenceOption[];
    lang: string;
    languages: StoreLanguage[];
    name: string;
    address: string;
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.pickupPoints.edit')"
        :action="update(pickupPoint)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit(pickupPoint, { query: { lang: code } })"
        />
        <StorePickupPointFormFields
            :errors="errors"
            :pickupPoint="pickupPoint"
            :staff-options="staffOptions"
            :key="lang"
            :name="name"
            :address="address"
        />
    </ResourceFormPage>
</template>
