<script setup lang="ts">
import { update } from '@/actions/App/Http/Controllers/Store/Admin/GoogleMapController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreGoogleMapFormFields from '@/components/StoreGoogleMapFormFields.vue';
import type { StoreGoogleMapSettings } from '@/components/StoreGoogleMapFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ settings: StoreGoogleMapSettings }>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.googleMap.title')"
        :action="update()"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreGoogleMapFormFields :settings="settings" :errors="errors" />
    </ResourceFormPage>
</template>
