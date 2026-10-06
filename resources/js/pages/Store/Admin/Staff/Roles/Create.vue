<script setup lang="ts">
import {
    index as rolesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreRoleFormFields from '@/components/StoreRoleFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { PermissionGroup } from '@/types/store';

defineProps<{
    permissionGroups: PermissionGroup[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.roles.create.title')"
        :action="store()"
        :submit-label="t('store.roles.create.submit')"
        :submitting-label="t('store.roles.create.submitting')"
        :cancel-href="rolesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreRoleFormFields
            :permission-groups="permissionGroups"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
