<script setup lang="ts">
import {
    index as pickupPointsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/PickupPointController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StorePickupPointFormFields from '@/components/StorePickupPointFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreStaffOption } from '@/types/store';

defineProps<{ staffOptions: StoreStaffOption[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.pickupPoints.create.title')"
        :action="store()"
        :submit-label="t('common.save')"
        :submitting-label="t('common.saving')"
        :cancel-href="pickupPointsIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StorePickupPointFormFields
            :staff-options="staffOptions"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
