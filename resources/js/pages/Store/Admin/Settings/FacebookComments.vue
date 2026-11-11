<script setup lang="ts">
import { updateFacebookComments } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import StoreIntegrationIdsForm from '@/components/StoreIntegrationIdsForm.vue';
import { integrationIdsValidation } from '@/lib/integrationIdsForm';
import type { StoreIntegrationId } from '@/types/store';

/** The Facebook comments switch and its app ID (over FACEBOOK_APP_ID). */
defineProps<{
    settings: Record<string, boolean>;
    ids: Record<string, StoreIntegrationId>;
    highlight?: string | null;
}>();
</script>

<template>
    <StoreIntegrationIdsForm
        section="facebookComments"
        :action="updateFacebookComments()"
        :settings="settings"
        :ids="ids"
        :highlight="highlight"
        :validation="integrationIdsValidation('facebookComments')"
    />
</template>
