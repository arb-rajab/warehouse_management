<script setup lang="ts">
import {
    index as trucksIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTruckFormFields from '@/components/StoreTruckFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreTruck, TruckPhoto } from '@/types/storeTruck';

defineProps<{ truck: StoreTruck; photos: TruckPhoto[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.trucks.edit.title', { name: truck.name })"
        :action="update(truck)"
        :submit-label="t('store.trucks.submit')"
        :submitting-label="t('store.trucks.submitting')"
        :cancel-href="trucksIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreTruckFormFields
            :truck="truck"
            :photos="photos"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
