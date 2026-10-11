<script setup lang="ts">
import {
    index as zonesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/ZoneController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreZoneFormFields from '@/components/StoreZoneFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCountry, StoreZone } from '@/types/store';

defineProps<{
    zone: StoreZone;
    countries: StoreCountry[];
    countryIds: number[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.zones.edit.title', { name: zone.name })"
        :action="update(zone)"
        :submit-label="t('common.save')"
        :submitting-label="t('common.saving')"
        :cancel-href="zonesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreZoneFormFields
            :zone="zone"
            :countries="countries"
            :country-ids="countryIds"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
