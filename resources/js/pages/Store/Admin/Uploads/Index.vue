<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import StoreUploadManager from '@/components/StoreUploadManager.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>
<template>
    <Head :title="t('store.uploads.library')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.uploads.library')" />
        <StoreUploadManager library initial-tab="library" />
    </StoreAdminLayout>
</template>
