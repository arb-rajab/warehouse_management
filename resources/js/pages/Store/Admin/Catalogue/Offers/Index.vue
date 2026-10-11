<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Download, Trash2 } from '@lucide/vue';
import {
    download,
    destroy,
    index as offersIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/OffersCatalogController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { formatDateTime } from '@/lib/date';
import { validateFileSize } from '@/lib/fileValidation';
import { t } from '@/lib/i18n';
import { usePageError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreOffersCatalog } from '@/types/store';

defineProps<{
    catalogs: Paginated<StoreOffersCatalog>;
    hasCatalog: boolean;
    filters: PerPageFilters;
}>();

const access = useStoreAccess();
const actionError = usePageError('action');

function onPerPageChange(perPage: number): void {
    router.get(
        offersIndex.url(),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.offersCatalog.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.offersCatalog.title')" />
        <ActionErrorBanner :message="actionError" />
        <Form
            v-if="access.can('add_offers_catalog') && !hasCatalog"
            :action="store()"
            #default="{ errors, processing }"
            class="mb-6 max-w-sm space-y-4"
        >
            <FormField
                id="catalog"
                type="file"
                @change="
                    validateFileSize(
                        $event,
                        50 * 1024 * 1024,
                        t('store.offersCatalog.limit'),
                    )
                "
                :label="t('store.offersCatalog.file')"
                :error="errors.catalog"
                accept=".pdf,application/pdf"
                required
                aria-describedby="catalog-limit"
            />
            <p
                id="catalog-limit"
                class="text-sm text-gray-600 dark:text-neutral-300"
            >
                {{ t('store.offersCatalog.limit') }}
            </p>
            <SubmitButton
                :label="t('store.offersCatalog.upload')"
                :processing-label="t('store.offersCatalog.uploading')"
                :processing="processing"
            />
        </Form>
        <DataTable
            :columns="[
                t('store.offersCatalog.file'),
                t('store.offersCatalog.createdAt'),
                t('store.offersCatalog.actions'),
            ]"
            :rows="catalogs.data"
            :empty-message="t('store.offersCatalog.empty')"
        >
            <template #row="{ row: catalog }">
                <td class="px-4 py-2">
                    <a
                        :href="download.url()"
                        class="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                    >
                        <Download class="h-3.5 w-3.5" />
                        {{ t('store.offersCatalog.download') }}
                    </a>
                </td>
                <td class="px-4 py-2">
                    {{
                        catalog.created_at
                            ? formatDateTime(catalog.created_at)
                            : '—'
                    }}
                </td>
                <td class="px-4 py-2">
                    <TableActionLink
                        v-if="access.can('delete_offers_catalog')"
                        :href="destroy(catalog, { mergeQuery: {} })"
                        variant="danger"
                        method="delete"
                        as="button"
                        :on-before="
                            () =>
                                confirmDelete(
                                    t('store.offersCatalog.deleteLabel'),
                                )
                        "
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        {{ t('store.offersCatalog.delete') }}
                    </TableActionLink>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="catalogs.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
