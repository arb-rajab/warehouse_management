<script setup lang="ts">
import { update } from '@/actions/App/Http/Controllers/Store/Admin/ProfileController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreProfileFormFields from '@/components/StoreProfileFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreProfile } from '@/types/store';
defineProps<{ profile: StoreProfile }>();
</script>
<template>
    <ResourceFormPage
        :title="t('store.profile.title')"
        :action="update(profile)"
        :layout="StoreAdminLayout"
        :submit-label="t('store.profile.save')"
        :submitting-label="t('store.profile.saving')"
        #default="{ errors }"
    >
        <StoreProfileFormFields :profile="profile" :errors="errors" />
    </ResourceFormPage>
</template>
