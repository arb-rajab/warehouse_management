<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    destroy,
    edit,
    index as catalogueIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/ColorController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColorFormFields from '@/components/StoreColorFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreColor } from '@/types/storeCatalogue';

const props = defineProps<{
    colors: Paginated<StoreColor>;
    filters: PerPageFilters & { search: string };
}>();
const access = useStoreAccess();
const currentPerPage = computed(() => props.filters.per_page ?? 20);
function onPerPageChange(perPage: number): void {
    router.get(
        catalogueIndex().url,
        { search: props.filters.search, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <div>
        <Head :title="t('store.colors.index.title')" />
        <StoreAdminLayout>
            <PageHeader :title="t('store.colors.index.title')" />
            <Form
                :action="catalogueIndex()"
                :options="{ preserveState: true, replace: true }"
                class="mb-4 flex items-end gap-3"
                #default="{ processing }"
            >
                <FormField
                    id="search"
                    :label="t('store.colors.index.search')"
                    :value="filters.search"
                    maxlength="255"
                />
                <input type="hidden" name="per_page" :value="currentPerPage" />
                <SubmitButton
                    :label="t('store.colors.index.searchSubmit')"
                    :processing-label="t('store.colors.index.searching')"
                    :processing="processing"
                />
            </Form>
            <DataTable
                :columns="[
                    t('store.colors.index.columnName'),
                    t('store.colors.index.columnCode'),
                    t('store.colors.index.columnActions'),
                ]"
                :rows="colors.data"
                :empty-message="t('store.colors.index.empty')"
            >
                <template #row="{ row }">
                    <td
                        class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{ row.name }}
                    </td>
                    <td class="px-4 py-2">
                        <span
                            class="me-2 inline-block h-4 w-4 rounded border border-gray-300"
                            :style="{ backgroundColor: row.code }"
                            aria-hidden="true"
                        />{{ row.code }}
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <TableActionLink
                                v-if="access.can('edit_color')"
                                :href="edit(row)"
                                ><Pencil class="h-3.5 w-3.5" />{{
                                    t('store.colors.index.edit')
                                }}</TableActionLink
                            >
                            <TableActionLink
                                v-if="access.can('delete_color')"
                                :href="destroy(row, { mergeQuery: {} })"
                                variant="danger"
                                method="delete"
                                as="button"
                                :on-before="() => confirmDelete(row.name)"
                                ><Trash2 class="h-3.5 w-3.5" />{{
                                    t('store.colors.index.delete')
                                }}</TableActionLink
                            >
                        </div>
                    </td>
                </template>
            </DataTable>
            <Pagination
                :links="colors.meta.links"
                :per-page="currentPerPage"
                @update:per-page="onPerPageChange"
            />
            <section
                v-if="access.can('edit_color')"
                class="mt-6 max-w-sm space-y-4"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.colors.index.add') }}
                </h2>
                <Form
                    :action="store()"
                    reset-on-success
                    #default="{ errors, processing }"
                    class="space-y-4"
                >
                    <StoreColorFormFields :errors="errors" />
                    <SubmitButton
                        :label="t('store.colors.fields.save')"
                        :processing-label="t('store.colors.fields.saving')"
                        :processing="processing"
                    />
                </Form>
            </section>
        </StoreAdminLayout>
    </div>
</template>
