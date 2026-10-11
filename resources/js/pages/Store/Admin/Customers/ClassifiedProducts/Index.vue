<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Eye, Trash2 } from '@lucide/vue';
import {
    destroy,
    index as productsIndex,
    publish,
} from '@/actions/App/Http/Controllers/Store/Admin/ClassifiedProductController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { ClassifiedProduct } from '@/types/store';

defineProps<{
    products: Paginated<ClassifiedProduct>;
    filters: PerPageFilters;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        productsIndex().url,
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
            :empty-message="t('store.classifiedProducts.empty')"
            :columns="[
                t('store.classifiedProducts.name'),
                t('store.classifiedProducts.image'),
                t('store.classifiedProducts.uploadedBy'),
                t('store.classifiedProducts.customerStatus'),
                t('store.classifiedProducts.published'),
                t('store.classifiedProducts.actions'),
            ]"
        >
            <template #row="{ row: product }">
                <td class="px-4 py-2">
                    <a
                        :href="product.public_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ product.name }}</a
                    >
                </td>
                <td class="px-4 py-2">
                    <img
                        v-if="product.thumbnail_url"
                        :src="product.thumbnail_url"
                        :alt="t('store.classifiedProducts.image')"
                        class="h-12 w-12 object-contain"
                    />
                </td>
                <td class="px-4 py-2">{{ product.added_by }}</td>
                <td class="px-4 py-2">
                    {{
                        t(
                            product.status
                                ? 'store.classifiedProducts.active'
                                : 'store.classifiedProducts.inactive',
                        )
                    }}
                </td>
                <td class="px-4 py-2">
                    <Form
                        v-if="access.can('publish_classified_product')"
                        :action="publish(product, { mergeQuery: {} })"
                        :options="{ preserveScroll: true }"
                        v-slot="{ errors, processing }"
                    >
                        <input
                            type="hidden"
                            name="published"
                            :value="product.published ? 0 : 1"
                        />
                        <SubmitButton
                            :processing="processing"
                            :label="
                                t(
                                    product.published
                                        ? 'store.classifiedProducts.unpublish'
                                        : 'store.classifiedProducts.publish',
                                )
                            "
                            :processing-label="
                                t('store.classifiedProducts.saving')
                            "
                        />
                        <FieldError :message="errors.published" />
                    </Form>
                    <span v-else class="inline-flex items-center gap-1"
                        ><Check v-if="product.published" class="h-4 w-4" />{{
                            t(
                                product.published
                                    ? 'store.classifiedProducts.active'
                                    : 'store.classifiedProducts.inactive',
                            )
                        }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <a
                            :href="product.public_url"
                            class="inline-flex items-center gap-1"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><Eye class="h-3.5 w-3.5" />{{
                                t('store.classifiedProducts.view')
                            }}</a
                        >
                        <TableActionLink
                            v-if="access.can('delete_classified_product')"
                            :href="destroy(product, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(product.name)"
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
