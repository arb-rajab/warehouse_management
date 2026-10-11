<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as uploadedFilesIndex } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import PageHeader from '@/components/PageHeader.vue';
import StoreFileUploader from '@/components/StoreFileUploader.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { filterClearButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
</script>

<template>
    <Head :title="t('store.uploads.create.title')" />

    <StoreAdminLayout>
        <PageHeader :title="t('store.uploads.create.title')">
            <Link :href="uploadedFilesIndex()" :class="filterClearButtonClass">
                {{ t('store.uploads.create.back') }}
            </Link>
        </PageHeader>

        <section
            class="rounded-lg border border-gray-200 bg-white p-6 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <h2 class="mb-4 text-sm font-semibold">
                {{ t('store.uploads.create.heading') }}
            </h2>
            <StoreFileUploader />
        </section>
    </StoreAdminLayout>
</template>
