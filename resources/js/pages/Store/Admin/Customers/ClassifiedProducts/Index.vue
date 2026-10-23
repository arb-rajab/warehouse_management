<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import {
    customer_product_index as classifiedProductsIndex,
    destroy_by_admin as destroy,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ClassifiedProductController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreClassifiedProduct } from '@/types/store';

defineProps<{
    products: Paginated<StoreClassifiedProduct>;
    filters: PerPageFilters;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        classifiedProductsIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.classifiedProducts.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.classifiedProducts.title')" />
        <DataTable
            :rows="products.data"
            :columns="[
                t('store.classifiedProducts.number'),
                t('store.classifiedProducts.name'),
                t('store.classifiedProducts.image'),
                t('store.classifiedProducts.uploadedBy'),
                t('store.classifiedProducts.customerStatus'),
                t('store.classifiedProducts.published'),
                t('store.classifiedProducts.actions'),
            ]"
            :empty-message="t('store.classifiedProducts.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    {{ (products.meta.from ?? 1) + products.data.indexOf(row) }}
                </td>
                <td class="px-4 py-2">
                    <a
                        :href="row.public_url"
                        target="_blank"
                        rel="noopener"
                        class="underline"
                        >{{ row.name }}</a
                    >
                </td>
                <td class="px-4 py-2">
                    <img
                        :src="row.thumbnail_url"
                        :alt="t('store.classifiedProducts.image')"
                        class="h-12 w-12 object-contain"
                    />
                </td>
                <td class="px-4 py-2">{{ row.added_by }}</td>
                <td class="px-4 py-2">
                    <span
                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                        :class="
                            row.status
                                ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                                : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
                        "
                        >{{
                            t(
                                row.status
                                    ? 'store.classifiedProducts.statusPublished'
                                    : 'store.classifiedProducts.statusUnpublished',
                            )
                        }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <Form
                        v-if="access.can('publish_classified_product')"
                        v-slot="{ processing, errors }"
                        :action="updatePublished(row, { mergeQuery: {} })"
                        :options="{ preserveScroll: true }"
                    >
                        <input
                            type="hidden"
                            name="status"
                            :value="row.published ? 0 : 1"
                        />
                        <button
                            type="submit"
                            role="switch"
                            :aria-checked="row.published"
                            :aria-label="
                                t('store.classifiedProducts.toggle', {
                                    name: row.name,
                                })
                            "
                            :disabled="processing"
                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:text-white"
                        >
                            <span
                                aria-hidden="true"
                                class="relative h-6 w-11 rounded-full"
                                :class="
                                    row.published
                                        ? 'bg-green-700 dark:bg-green-600'
                                        : 'bg-gray-500 dark:bg-neutral-600'
                                "
                                ><span
                                    class="absolute top-1 h-4 w-4 rounded-full bg-white"
                                    :class="
                                        row.published ? 'end-1' : 'start-1'
                                    "
                            /></span>
                            {{
                                t(
                                    row.published
                                        ? 'store.classifiedProducts.unpublish'
                                        : 'store.classifiedProducts.publish',
                                )
                            }}
                        </button>
                        <FieldError :message="errors.status" />
                    </Form>
                    <button
                        v-else
                        type="button"
                        role="switch"
                        :aria-checked="row.published"
                        :aria-label="
                            t('store.classifiedProducts.toggle', {
                                name: row.name,
                            })
                        "
                        disabled
                        class="cursor-not-allowed text-gray-500 dark:text-neutral-400"
                    >
                        {{
                            t(
                                row.published
                                    ? 'store.classifiedProducts.statusPublished'
                                    : 'store.classifiedProducts.statusUnpublished',
                            )
                        }}
                    </button>
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <a
                            :href="row.public_url"
                            target="_blank"
                            rel="noopener"
                            class="underline"
                            >{{ t('store.classifiedProducts.show') }}</a
                        >
                        <TableActionLink
                            v-if="access.can('delete_classified_product')"
                            :href="destroy(row, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(row.name ?? '')"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.classifiedProducts.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="products.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
