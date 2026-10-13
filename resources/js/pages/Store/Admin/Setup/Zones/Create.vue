<script setup lang="ts">
import {
    store,
    index as resourceIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/ZoneController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreZoneFormFields from '@/components/StoreZoneFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreReferenceOption } from '@/types/store';
defineProps<{ countries: StoreReferenceOption[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.zones.add')"
        :action="store()"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreZoneFormFields :errors="errors" :countries="countries" />
    </ResourceFormPage>
</template>
