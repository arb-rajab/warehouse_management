<script setup lang="ts">
import {
    index as driversIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDriverFormFields from '@/components/StoreDriverFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCountry } from '@/types/store';

defineProps<{ countries: Pick<StoreCountry, 'id' | 'name'>[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.drivers.create.title')"
        :action="store()"
        :submit-label="t('store.drivers.create.submit')"
        :submitting-label="t('store.drivers.create.submitting')"
        :cancel-href="driversIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreDriverFormFields :countries="countries" :errors="errors" />
    </ResourceFormPage>
</template>
