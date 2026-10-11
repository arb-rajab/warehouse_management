<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    destroy,
    edit,
    index as categoriesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogCategoryController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreBlogCategory } from '@/types/store';

const props = defineProps<{
    categories: Paginated<StoreBlogCategory>;
    filters: PerPageFilters & { search?: string | null };
}>();

const access = useStoreAccess();

const currentPerPage = computed(() => props.filters.per_page ?? 20);

function onPerPageChange(perPage: number): void {
    router.get(
        categoriesIndex().url,
        { per_page: perPage, search: props.filters.search },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.blogCategories.index.title')" />

    <StoreAdminLayout>
        <PageHeader :title="t('store.blogCategories.index.title')">
            <AddResourceLink
                v-if="access.can('add_blog_category')"
                :href="create()"
                :label="t('store.blogCategories.index.add')"
            />
        </PageHeader>

        <Form
            :action="categoriesIndex()"
            #default="{ errors, processing }"
            class="mb-4 flex items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="currentPerPage" />
            <FormField
                id="search"
                :label="t('store.blogCategories.index.search')"
                :value="filters.search ?? ''"
                :error="errors.search"
                maxlength="255"
            />
            <SubmitButton
                :label="t('store.blogCategories.index.search')"
                :processing-label="t('store.blogCategories.index.searching')"
                :processing="processing"
            />
        </Form>

        <DataTable
            :columns="[
                t('store.blogCategories.index.columnName'),
                t('store.blogCategories.index.columnActions'),
            ]"
            :rows="categories.data"
            :empty-message="t('store.blogCategories.index.empty')"
        >
            <template #row="{ row: category }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{ category.category_name }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_blog_category')"
                            :href="edit(category)"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('store.blogCategories.index.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            v-if="access.can('delete_blog_category')"
                            :href="destroy(category, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="
                                () => confirmDelete(category.category_name)
                            "
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('store.blogCategories.index.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="categories.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
