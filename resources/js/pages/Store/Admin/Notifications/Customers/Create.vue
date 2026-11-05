<script setup lang="ts">
import {
    index as notificationsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerNotificationController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerNotificationFormFields from '@/components/StoreCustomerNotificationFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <ResourceFormPage
        :title="t('store.notifications.create.title')"
        :action="store()"
        :submit-label="t('store.notifications.create.submit')"
        :submitting-label="t('store.notifications.create.submitting')"
        :cancel-href="notificationsIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreCustomerNotificationFormFields :errors="errors" />
    </ResourceFormPage>
</template>
