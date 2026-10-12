<script setup lang="ts">
import { Download } from '@lucide/vue';
import {
    bulkUpload,
    pdfDownloadBrand,
    pdfDownloadCategory,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductBulkUploadController';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { validateFileSize } from '@/lib/fileValidation';
import { t } from '@/lib/i18n';

defineProps<{ exampleUrl: string }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.bulkUpload.title')"
        :action="bulkUpload()"
        :layout="StoreAdminLayout"
        :submit-label="t('store.bulkUpload.upload')"
        :submitting-label="t('store.bulkUpload.uploading')"
        wide
    >
        <template #default="{ errors }">
            <ol class="list-inside list-decimal space-y-2">
                <li>{{ t('store.bulkUpload.prepare') }}</li>
                <li>{{ t('store.bulkUpload.identifiers') }}</li>
                <li>{{ t('store.bulkUpload.editAfter') }}</li>
            </ol>
            <div class="flex flex-wrap gap-4">
                <a
                    :href="exampleUrl"
                    download
                    class="inline-flex items-center gap-1 underline"
                    ><Download class="h-4 w-4" />{{
                        t('store.bulkUpload.example')
                    }}</a
                >
                <a
                    :href="pdfDownloadCategory().url"
                    class="inline-flex items-center gap-1 underline"
                    ><Download class="h-4 w-4" />{{
                        t('store.bulkUpload.categories')
                    }}</a
                >
                <a
                    :href="pdfDownloadBrand().url"
                    class="inline-flex items-center gap-1 underline"
                    ><Download class="h-4 w-4" />{{
                        t('store.bulkUpload.brands')
                    }}</a
                >
            </div>
            <FormField
                id="bulk_file"
                type="file"
                @change="
                    validateFileSize(
                        $event,
                        10 * 1024 * 1024,
                        t('store.bulkUpload.limit'),
                    )
                "
                required
                accept=".xlsx,.xls,.csv,.txt"
                :label="t('store.bulkUpload.file')"
                :error="errors.bulk_file"
            />
            <p>{{ t('store.bulkUpload.limit') }}</p>
        </template>
    </ResourceFormPage>
</template>
