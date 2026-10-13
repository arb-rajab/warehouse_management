<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2, Eye } from '@lucide/vue';
import { computed } from 'vue';
import {
    destroy,
    edit,
    index as catalogueIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeController';
import { index as valuesIndex } from '@/actions/App/Http/Controllers/Store/Admin/AttributeValueController';
import DataTable from '@/components/DataTable.vue';

import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreAttributeFormFields from '@/components/StoreAttributeFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreAttribute } from '@/types/storeCatalogue';

const props = defineProps<{
    attributes: Paginated<StoreAttribute>;
    filters: PerPageFilters;
}>();
const access = useStoreAccess();
const currentPerPage = computed(() => props.filters.per_page ?? 20);
function onPerPageChange(perPage: number): void {
    router.get(
        catalogueIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <div>
        <Head :title="t('store.attributes.index.title')" />
        <StoreAdminLayout>
            <PageHeader :title="t('store.attributes.index.title')" />

            <DataTable
                :columns="[
                    t('store.attributes.index.columnName'),
                    t('store.attributes.index.columnValues'),
                    t('store.attributes.index.columnActions'),
                ]"
                :rows="attributes.data"
                :empty-message="t('store.attributes.index.empty')"
            >
                <template #row="{ row }">
                    <td
                        class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{ row.name }}
                    </td>
                    <td class="px-4 py-2">
                        <span
                            v-for="value in row.values"
                            :key="value.id"
                            class="me-2"
                            >{{ value.value }}</span
                        >
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <TableActionLink
                                v-if="
                                    access.can('view_product_attribute_values')
                                "
                                :href="valuesIndex(row)"
                                ><Eye class="h-3.5 w-3.5" />{{
                                    t('store.attributes.index.manageValues')
                                }}</TableActionLink
                            >
                            <TableActionLink
                                v-if="access.can('edit_product_attribute')"
                                :href="edit(row)"
                                ><Pencil class="h-3.5 w-3.5" />{{
                                    t('store.attributes.index.edit')
                                }}</TableActionLink
                            >
                            <TableActionLink
                                v-if="access.can('delete_product_attribute')"
                                :href="destroy(row, { mergeQuery: {} })"
                                variant="danger"
                                method="delete"
                                as="button"
                                :on-before="() => confirmDelete(row.name)"
                                ><Trash2 class="h-3.5 w-3.5" />{{
                                    t('store.attributes.index.delete')
                                }}</TableActionLink
                            >
                        </div>
                    </td>
                </template>
            </DataTable>
            <Pagination
                :links="attributes.meta.links"
                :per-page="currentPerPage"
                @update:per-page="onPerPageChange"
            />
            <section
                v-if="access.can('edit_product_attribute')"
                class="mt-6 max-w-sm space-y-4"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.attributes.index.add') }}
                </h2>
                <Form
                    :action="store()"
                    reset-on-success
                    #default="{ errors, processing }"
                    class="space-y-4"
                >
                    <StoreAttributeFormFields :errors="errors" />
                    <SubmitButton
                        :label="t('store.attributes.fields.save')"
                        :processing-label="t('store.attributes.fields.saving')"
                        :processing="processing"
                    />
                </Form>
            </section>
        </StoreAdminLayout>
    </div>
</template>
