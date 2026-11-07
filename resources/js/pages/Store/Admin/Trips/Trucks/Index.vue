<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Printer } from '@lucide/vue';
import { ref, computed } from 'vue';
import { show as showTrip } from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import {
    create,
    edit,
    index as trucksIndex,
    qr,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { StoreTruck, TruckPhoto } from '@/types/storeTruck';

const props = defineProps<{
    trucks: Paginated<StoreTruck>;
    photos: TruckPhoto[];
    filters: { search: string; per_page: number };
}>();
const access = useStoreAccess();
const search = ref(props.filters.search);

function filter(perPage = props.filters.per_page): void {
    router.get(
        trucksIndex().url,
        { search: search.value, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
function photoUrl(truck: StoreTruck): string | null {
    return (
        props.photos.find((photo) => photo.id === truck.photos[0])?.url ?? null
    );
}
const columns = useTableColumns({
    listId: 'trips.trucks',
    columns: [
        { key: 'name', label: 'store.trucks.fields.name', required: true },
        { key: 'id', label: 'store.trucks.index.id' },
        { key: 'model', label: 'store.trucks.fields.model' },
        { key: 'capacity', label: 'store.trucks.fields.capacity' },
        { key: 'description', label: 'store.trucks.fields.description' },
        { key: 'plate', label: 'store.trucks.fields.plate' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.trucks.index.actions'),
]);
</script>

<template>
    <Head :title="t('store.trucks.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.trucks.index.title')">
            <AddResourceLink
                v-if="access.can('add_truck')"
                :href="create()"
                :label="t('store.trucks.index.add')"
            />
        </PageHeader>
        <form class="mb-4 max-w-sm" @submit.prevent="filter()">
            <FormField
                id="search"
                :label="t('store.trucks.index.search')"
                :value="search"
                maxlength="255"
                @input="search = ($event.target as HTMLInputElement).value"
            />
        </form>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="trucks.data"
            :empty-message="t('store.trucks.index.empty')"
        >
            <template #row="{ row: truck }">
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <img
                            v-if="photoUrl(truck)"
                            :src="photoUrl(truck) ?? ''"
                            alt=""
                            class="h-8 w-8 rounded object-cover"
                        /><TableLink
                            v-if="
                                truck.open_trip_id &&
                                access.can('view_all_trips')
                            "
                            :href="showTrip(truck.open_trip_id)"
                            >{{ truck.name }}</TableLink
                        ><span v-else>{{ truck.name }}</span>
                    </div>
                </td>
                <td v-if="shown.id" class="px-4 py-2">{{ truck.id }}</td>
                <td v-if="shown.model" class="px-4 py-2">{{ truck.model }}</td>
                <td v-if="shown.capacity" class="px-4 py-2">
                    {{ truck.max_pallets_number }}
                </td>
                <td
                    v-if="shown.description"
                    class="max-w-xs truncate px-4 py-2"
                    :title="truck.description ?? undefined"
                >
                    {{ truck.description }}
                </td>
                <td v-if="shown.plate" class="px-4 py-2">
                    {{ truck.license_plate }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_truck')"
                            :href="edit(truck)"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.trucks.index.edit')
                            }}</TableActionLink
                        ><a
                            v-if="truck.license_plate"
                            :href="qr.url(truck)"
                            class="inline-flex items-center gap-1 text-sm text-blue-700 underline dark:text-blue-300"
                            ><Printer class="h-3.5 w-3.5" />{{
                                t('store.trucks.index.print')
                            }}</a
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="trucks.meta.links"
            :per-page="filters.per_page"
            @update:per-page="filter"
        />
    </StoreAdminLayout>
</template>
