<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Printer } from '@lucide/vue';
import { ref } from 'vue';
import {
    create,
    edit,
    index as trucksIndex,
    qr,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import { show as showTrip } from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
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
        <DataTable
            :columns="[
                t('store.trucks.fields.name'),
                t('store.trucks.index.id'),
                t('store.trucks.fields.model'),
                t('store.trucks.fields.capacity'),
                t('store.trucks.fields.description'),
                t('store.trucks.fields.plate'),
                t('store.trucks.index.actions'),
            ]"
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
                            class="h-12 w-12 rounded object-cover"
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
                <td class="px-4 py-2">{{ truck.id }}</td>
                <td class="px-4 py-2">{{ truck.model }}</td>
                <td class="px-4 py-2">{{ truck.max_pallets_number }}</td>
                <td class="px-4 py-2">{{ truck.description }}</td>
                <td class="px-4 py-2">{{ truck.license_plate }}</td>
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
