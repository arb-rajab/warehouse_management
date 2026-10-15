<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpRight, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { edit as homeEdit } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHomeController';
import {
    create,
    destroy,
    edit,
    index as pagesIndex,
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
}>();
const access = useStoreAccess();
const currentPerPage = computed(() => props.filters.per_page ?? 20);
function onPerPageChange(perPage: number): void {
    router.get(
        pagesIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.website.pages.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.website.pages.title')">
            <AddResourceLink
                v-if="access.can('add_website_page')"
                :href="create()"
                :label="t('store.website.pages.add')"
            />
        </PageHeader>
        <DataTable
            :columns="[
                t('store.website.pages.name'),
                t('store.website.pages.url'),
                t('store.website.pages.actions'),
            ]"
            :rows="pages.data"
            :empty-message="t('store.website.pages.empty')"
        >
            <template #row="{ row: page }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    <a
                        v-if="page.public_url"
                        :href="page.public_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 hover:underline"
                        >{{ page.title }}<ArrowUpRight class="size-3.5"
                    /></a>
                    <template v-else>{{ page.title }}</template>
                </td>
                <td class="px-4 py-2 break-all" dir="ltr">
                    {{ page.public_url ?? '—' }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <TableActionLink
                            v-if="
                                access.can('edit_website_page') &&
                                page.type === 'home_page'
                            "
                            :href="homeEdit()"
                            ><Pencil class="size-3.5" />{{
                                t('store.website.pages.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-else-if="
                                access.can('edit_website_page') && page.slug
                            "
                            :href="edit(page.slug)"
                            ><Pencil class="size-3.5" />{{
                                t('store.website.pages.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="
                                access.can('delete_website_page') &&
                                page.type === 'custom_page'
                            "
                            :href="destroy(page, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(page.title)"
                            ><Trash2 class="size-3.5" />{{
                                t('store.website.pages.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="pages.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
