<script setup lang="ts">
import {
    index as tripsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTripFormFields from '@/components/StoreTripFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreReferenceOption,
    StoreTripForm,
    StoreTripOrderOption,
} from '@/types/store';

defineProps<{
    trip: StoreTripForm;
    orders: StoreTripOrderOption[];
    customers: StoreReferenceOption[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.trips.edit.title', { code: trip.code })"
        :action="update(trip)"
        :submit-label="t('store.trips.edit.submit')"
        :submitting-label="t('store.trips.edit.submitting')"
        :cancel-href="tripsIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreTripFormFields
            :trip="trip"
            :orders="orders"
            :customers="customers"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
