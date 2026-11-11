<script setup lang="ts">
import { updateFacebookChat } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import StoreIntegrationIdsForm from '@/components/StoreIntegrationIdsForm.vue';
import { integrationIdsValidation } from '@/lib/integrationIdsForm';
import type { StoreIntegrationId } from '@/types/store';

/** The Facebook chat switch and its page ID (over FACEBOOK_PAGE_ID). */
defineProps<{
    settings: Record<string, boolean>;
    ids: Record<string, StoreIntegrationId>;
    highlight?: string | null;
}>();
</script>

<template>
    <StoreIntegrationIdsForm
        section="facebookChat"
        :action="updateFacebookChat()"
        :settings="settings"
        :ids="ids"
        :highlight="highlight"
        :validation="integrationIdsValidation('facebookChat')"
    />
</template>
