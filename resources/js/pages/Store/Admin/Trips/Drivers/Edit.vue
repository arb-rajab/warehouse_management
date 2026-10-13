<script setup lang="ts">
import {
    index as driversIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDriverFormFields from '@/components/StoreDriverFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCountry, StoreDriver } from '@/types/store';

defineProps<{
    driver: StoreDriver;
    countries: Pick<StoreCountry, 'id' | 'name'>[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.drivers.edit.title', { name: driver.name })"
        :action="update(driver)"
        :submit-label="t('store.drivers.edit.submit')"
        :submitting-label="t('store.drivers.edit.submitting')"
        :cancel-href="driversIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreDriverFormFields
            :countries="countries"
            :name="driver.name"
            :email="driver.email"
            :phone="driver.phone"
            :country="driver.country"
            :errors="errors"
            editing
        />
    </ResourceFormPage>
</template>
