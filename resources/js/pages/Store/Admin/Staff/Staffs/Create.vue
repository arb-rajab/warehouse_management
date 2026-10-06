<script setup lang="ts">
import {
    index as staffIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreStaffFormFields from '@/components/StoreStaffFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreRole } from '@/types/store';

defineProps<{
    roles: StoreRole[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.staff.create.title')"
        :action="store()"
        :submit-label="t('store.staff.create.submit')"
        :submitting-label="t('store.staff.create.submitting')"
        :cancel-href="staffIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreStaffFormFields
            :roles="roles"
            :errors="errors"
            password-required
        />
    </ResourceFormPage>
</template>
