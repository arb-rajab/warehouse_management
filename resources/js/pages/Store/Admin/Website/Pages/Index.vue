<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { edit as homeEdit } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHomeController';
import {
    create,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import {
    index as pagesIndex,
    destroy,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreWebsitePage } from '@/types/store';
const props = defineProps<{
    pages: Paginated<StoreWebsitePage>;
    filters: PerPageFilters;
    defaultLanguage: string;
}>();
const access = useStoreAccess();
function editPage(page: StoreWebsitePage) {
    return page.type === 'home_page'
        ? homeEdit()
        : edit(page, { query: { lang: props.defaultLanguage } });
}
function onPerPageChange(perPage: number): void {
    router.get(
        pagesIndex.url(),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head :title="t('store.website.pages.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.website.pages.title')"
            ><AddResourceLink
                v-if="access.can('add_website_page')"
                :href="create()"
                :label="t('store.website.pages.add')"
        /></PageHeader>
        <DataTable
            :columns="[
                t('store.website.pages.name'),
                t('store.website.pages.url'),
                t('store.support.actions'),
            ]"
            :rows="pages.data"
            :empty-message="t('store.website.pages.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <a :href="row.public_url" class="underline">{{
                        row.title
                    }}</a>
                </td>
                <td class="px-4 py-2">{{ row.public_url }}</td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink
                            v-if="access.can('edit_website_page')"
                            :href="editPage(row)"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.website.pages.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="
                                row.type === 'custom_page' &&
                                access.can('delete_website_page')
                            "
                            :href="destroy(row, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(row.title)"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.support.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="pages.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
