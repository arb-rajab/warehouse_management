<script setup lang="ts">
import {
    index as staffIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreStaffFormFields from '@/components/StoreStaffFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StaffMember, StoreRole } from '@/types/store';

defineProps<{
    staffMember: StaffMember;
    roles: StoreRole[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.staff.edit.title', { name: staffMember.name })"
        :action="update(staffMember)"
        :submit-label="t('store.staff.edit.submit')"
        :submitting-label="t('store.staff.edit.submitting')"
        :cancel-href="staffIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreStaffFormFields
            :roles="roles"
            :name="staffMember.name"
            :email="staffMember.email"
            :phone="staffMember.phone"
            :role-id="staffMember.role?.id ?? null"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
