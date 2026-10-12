<script setup lang="ts">
import {
    update,
    index as resourceIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/ZoneController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreZoneFormFields from '@/components/StoreZoneFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreZone, StoreReferenceOption } from '@/types/store';
defineProps<{
    zone: StoreZone;
    countries: StoreReferenceOption[];
    countryIds: number[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.zones.edit')"
        :action="update(zone)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreZoneFormFields
            :errors="errors"
            :zone="zone"
            :countries="countries"
            :country-ids="countryIds"
        />
    </ResourceFormPage>
</template>
