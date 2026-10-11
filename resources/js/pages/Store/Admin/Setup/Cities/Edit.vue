<script setup lang="ts">
import {
    update,
    index as resourceIndex,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCityFormFields from '@/components/StoreCityFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreCity,
    StoreReferenceOption,
    StoreLanguage,
} from '@/types/store';
defineProps<{
    city: StoreCity;
    states: StoreReferenceOption[];
    lang: string;
    languages: StoreLanguage[];
    name: string;
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.cities.edit')"
        :action="update(city)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit(city, { query: { lang: code } })"
        />
        <StoreCityFormFields
            :errors="errors"
            :city="city"
            :states="states"
            :key="lang"
            :name="name"
        />
    </ResourceFormPage>
</template>
