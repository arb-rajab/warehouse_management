<script setup lang="ts">
import {
    index as zonesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/ZoneController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreZoneFormFields from '@/components/StoreZoneFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCountry } from '@/types/store';

defineProps<{ countries: StoreCountry[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.zones.create.title')"
        :action="store()"
        :submit-label="t('common.save')"
        :submitting-label="t('common.saving')"
        :cancel-href="zonesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreZoneFormFields :countries="countries" :errors="errors" />
    </ResourceFormPage>
</template>
