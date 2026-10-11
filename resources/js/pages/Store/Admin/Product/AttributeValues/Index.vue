<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    destroy,
    edit,
    index as catalogueIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeValueController';
import DataTable from '@/components/DataTable.vue';

import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreAttributeValueFormFields from '@/components/StoreAttributeValueFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type {
    StoreAttributeValue,
    StoreAttribute,
} from '@/types/storeCatalogue';

const props = defineProps<{
    values: Paginated<StoreAttributeValue>;
    filters: PerPageFilters;
    attribute: StoreAttribute;
}>();
const access = useStoreAccess();
const currentPerPage = computed(() => props.filters.per_page ?? 20);
function onPerPageChange(perPage: number): void {
    router.get(
        catalogueIndex(props.attribute).url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <div>
        <Head
            :title="
                t('store.attributeValues.index.title', { name: attribute.name })
            "
        />
        <StoreAdminLayout>
            <PageHeader
                :title="
                    t('store.attributeValues.index.title', {
                        name: attribute.name,
                    })
                "
            />

            <DataTable
                :columns="[
                    t('store.attributeValues.index.columnValue'),
                    t('store.attributeValues.index.columnActions'),
                ]"
                :rows="values.data"
                :empty-message="t('store.attributeValues.index.empty')"
            >
                <template #row="{ row }">
                    <td
                        class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{ row.value }}
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <TableActionLink
                                v-if="
                                    access.can('edit_product_attribute_value')
                                "
                                :href="edit(row)"
                                ><Pencil class="h-3.5 w-3.5" />{{
                                    t('store.attributeValues.index.edit')
                                }}</TableActionLink
                            >
                            <TableActionLink
                                v-if="
                                    access.can('delete_product_attribute_value')
                                "
                                :href="destroy(row, { mergeQuery: {} })"
                                variant="danger"
                                method="delete"
                                as="button"
                                :on-before="() => confirmDelete(row.value)"
                                ><Trash2 class="h-3.5 w-3.5" />{{
                                    t('store.attributeValues.index.delete')
                                }}</TableActionLink
                            >
                        </div>
                    </td>
                </template>
            </DataTable>
            <Pagination
                :links="values.meta.links"
                :per-page="currentPerPage"
                @update:per-page="onPerPageChange"
            />
            <section
                v-if="access.can('edit_product_attribute_value')"
                class="mt-6 max-w-sm space-y-4"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.attributeValues.index.add') }}
                </h2>
                <Form
                    :action="store()"
                    reset-on-success
                    #default="{ errors, processing }"
                    class="space-y-4"
                >
                    <StoreAttributeValueFormFields
                        :attribute-id="attribute.id"
                        :errors="errors"
                    />
                    <SubmitButton
                        :label="t('store.attributeValues.fields.save')"
                        :processing-label="
                            t('store.attributeValues.fields.saving')
                        "
                        :processing="processing"
                    />
                </Form>
            </section>
        </StoreAdminLayout>
    </div>
</template>
