<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    create,
    destroy,
    edit,
    index as driversIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreDriver } from '@/types/store';

const props = defineProps<{
    drivers: Paginated<StoreDriver>;
    filters: { per_page: number; search: string };
}>();
const access = useStoreAccess();

function search(event: SubmitEvent): void {
    const values = new FormData(event.target as HTMLFormElement);
    router.get(
        driversIndex().url,
        {
            search: String(values.get('search') ?? ''),
            per_page: props.filters.per_page,
        },
        { preserveState: true, replace: true },
    );
}

function onPerPageChange(perPage: number): void {
    router.get(
        driversIndex().url,
        { search: props.filters.search, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.drivers.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.drivers.index.title')">
            <AddResourceLink
                v-if="access.can('add_driver')"
                :href="create()"
                :label="t('store.drivers.index.add')"
            />
        </PageHeader>
        <form class="mb-4 max-w-sm" @submit.prevent="search">
            <FormField
                id="search"
                :label="t('store.drivers.index.search')"
                :value="filters.search"
                maxlength="191"
            />
        </form>
        <DataTable
            :columns="
                ['name', 'id', 'email', 'phone', 'country', 'actions'].map(
                    (column) => t(`store.drivers.index.${column}`),
                )
            "
            :rows="drivers.data"
            :empty-message="t('store.drivers.index.empty')"
        >
            <template #row="{ row: driver }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{ driver.name }}
                </td>
                <td class="px-4 py-2">{{ driver.id }}</td>
                <td class="px-4 py-2">{{ driver.email }}</td>
                <td class="px-4 py-2">{{ driver.phone }}</td>
                <td class="px-4 py-2">{{ driver.country }}</td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_driver')"
                            :href="edit(driver)"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.drivers.index.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_driver')"
                            :href="destroy(driver, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(driver.name)"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.drivers.index.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="drivers.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
