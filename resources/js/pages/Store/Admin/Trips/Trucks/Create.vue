<script setup lang="ts">
import {
    index as trucksIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTruckFormFields from '@/components/StoreTruckFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { TruckPhoto } from '@/types/storeTruck';

defineProps<{ photos: TruckPhoto[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.trucks.create.title')"
        :action="store()"
        :submit-label="t('store.trucks.submit')"
        :submitting-label="t('store.trucks.submitting')"
        :cancel-href="trucksIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreTruckFormFields :photos="photos" :errors="errors" />
    </ResourceFormPage>
</template>
