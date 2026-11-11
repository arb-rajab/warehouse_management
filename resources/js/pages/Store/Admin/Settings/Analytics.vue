<script setup lang="ts">
import { updateAnalytics } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import StoreIntegrationIdsForm from '@/components/StoreIntegrationIdsForm.vue';
import { integrationIdsValidation } from '@/lib/integrationIdsForm';
import type { StoreIntegrationId } from '@/types/store';

/**
 * Google Analytics and the Facebook pixel: their switches and public IDs. The
 * saved IDs win over TRACKING_ID and FACEBOOK_PIXEL_ID (StoreIntegrationIds).
 */
defineProps<{
    settings: Record<string, boolean>;
    ids: Record<string, StoreIntegrationId>;
    highlight?: string | null;
}>();
</script>

<template>
    <StoreIntegrationIdsForm
        section="analytics"
        :action="updateAnalytics['/temp/admin/google-analytics']()"
        :settings="settings"
        :ids="ids"
        :highlight="highlight"
        :validation="integrationIdsValidation('analytics')"
    />
</template>
