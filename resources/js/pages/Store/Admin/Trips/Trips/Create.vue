<script setup lang="ts">
import {
    index as tripsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTripFormFields from '@/components/StoreTripFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreReferenceOption, StoreTripOrderOption } from '@/types/store';

defineProps<{
    orders: StoreTripOrderOption[];
    customers: StoreReferenceOption[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.trips.create.title')"
        :action="store()"
        :submit-label="t('store.trips.create.submit')"
        :submitting-label="t('store.trips.create.submitting')"
        :cancel-href="tripsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreTripFormFields
            :orders="orders"
            :customers="customers"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
