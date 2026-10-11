<script setup lang="ts">
import {
    store,
    index as resourceIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/PickupPointController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StorePickupPointFormFields from '@/components/StorePickupPointFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreReferenceOption } from '@/types/store';
defineProps<{ staffOptions: StoreReferenceOption[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.pickupPoints.add')"
        :action="store()"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StorePickupPointFormFields
            :errors="errors"
            :staff-options="staffOptions"
        />
    </ResourceFormPage>
</template>
