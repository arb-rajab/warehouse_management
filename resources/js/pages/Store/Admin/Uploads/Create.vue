<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import StoreUploadManager from '@/components/StoreUploadManager.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
</script>
<template>
    <Head :title="t('store.uploads.upload')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.uploads.upload')" />
        <StoreUploadManager library initial-tab="upload" />
    </StoreAdminLayout>
</template>
