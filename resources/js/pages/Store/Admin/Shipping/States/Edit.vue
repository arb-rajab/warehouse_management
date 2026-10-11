<script setup lang="ts">
import {
    index as statesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/StateController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreStateFormFields from '@/components/StoreStateFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreCountry, StoreState } from '@/types/store';

defineProps<{ state: StoreState; countries: StoreCountry[] }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.states.edit.title', { name: state.name })"
        :action="update(state)"
        :submit-label="t('common.save')"
        :submitting-label="t('common.saving')"
        :cancel-href="statesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreStateFormFields
            :state="state"
            :countries="countries"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
