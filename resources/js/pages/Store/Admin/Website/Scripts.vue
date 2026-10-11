<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StoreWebsiteScriptsForm from '@/components/StoreWebsiteScriptsForm.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
defineProps<{ settings: { header_script: string; footer_script: string } }>();
</script>
<template>
    <Head :title="t('store.website.scripts.title')" /><StoreAdminLayout
        ><StoreWebsiteScriptsForm :settings="settings"
    /></StoreAdminLayout>
</template>
