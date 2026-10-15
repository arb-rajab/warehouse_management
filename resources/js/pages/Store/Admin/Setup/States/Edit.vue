<script setup lang="ts">
import {
    update,
    index as resourceIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/StateController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreStateFormFields from '@/components/StoreStateFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreState, StoreReferenceOption } from '@/types/store';
defineProps<{ state: StoreState; countries: StoreReferenceOption[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.states.edit')"
        :action="update(state)"
        :submit-label="t('store.reference.save')"
        :submitting-label="t('store.reference.saving')"
        :cancel-href="resourceIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreStateFormFields
            :errors="errors"
            :state="state"
            :countries="countries"
        />
    </ResourceFormPage>
</template>
