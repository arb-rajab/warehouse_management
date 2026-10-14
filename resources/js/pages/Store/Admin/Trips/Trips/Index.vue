<script setup lang="ts">
import { Form, Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, RefreshCw, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { edit as driverEdit } from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { shippingCreate } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import {
    bulkDestroy,
    destroy,
    index as allTrips,
    today as todayTrips,
    updateNotes,
} from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import { edit as truckEdit } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import {
    create as tripsCreate,
    edit as tripsEdit,
    show as tripsShow,
} from '@/actions/App/Http/Controllers/Trip/TripController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreTripStatusDialog from '@/components/StoreTripStatusDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { currentLocaleTag, formatDateTime, formatDuration } from '@/lib/date';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type {
    StoreTrip,
    StoreTripFilters,
    StoreTripListType,
    StoreTripStatus,
} from '@/types/store';

const props = defineProps<{
    listType: StoreTripListType;
    trips: Paginated<StoreTrip>;
    filters: StoreTripFilters;
    statuses: StoreTripStatus[];
}>();
const access = useStoreAccess();
const title = computed(() =>
    t(
        props.listType === 'today'
            ? 'store.trips.index.todayTitle'
            : 'store.trips.index.allTitle',
    ),
);
const listIndex = computed(() =>
    props.listType === 'today' ? todayTrips : allTrips,
);
/** Every row action names the list it came from and returns to it. */
const listQuery = computed(() => ({ mergeQuery: { list: props.listType } }));
const selected = ref<number[]>([]);
const allSelected = computed(
    () =>
        props.trips.data.length > 0 &&
        props.trips.data.every((trip) => selected.value.includes(trip.id)),
);
watch(
    () => props.trips.data.map((trip) => trip.id).join(','),
    () => {
        selected.value = [];
    },
);
const statusTrip = ref<StoreTrip | null>(null);
const statusOpen = ref(false);
const notesForm = useForm({ notes: '' });
const notesTripId = ref<number | null>(null);
const savedNotesTripId = ref<number | null>(null);

function selectAll(checked: boolean): void {
    selected.value = checked ? props.trips.data.map((trip) => trip.id) : [];
}
function openStatus(trip: StoreTrip): void {
    statusTrip.value = trip;
    statusOpen.value = true;
}
/** Saves a row's notes when the field changes, as the Blade list did. */
function saveNotes(trip: StoreTrip, event: Event): void {
    notesTripId.value = trip.id;
    savedNotesTripId.value = null;
    notesForm.notes = (event.target as HTMLTextAreaElement).value;
    notesForm.clearErrors();
    notesForm.submit(updateNotes(trip, listQuery.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            savedNotesTripId.value = trip.id;
        },
    });
}
/** Metres as kilometres to two places, as the Blade list printed them. */
function kilometres(metres: number | null): string {
    return new Intl.NumberFormat(currentLocaleTag(), {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format((metres ?? 0) / 1000);
}
/** The status filter's own error or its first refused entry (`status.0`). */
function statusError(errors: Record<string, string>): string | undefined {
    return (
        errors.status ??
        Object.entries(errors).find(([key]) => key.startsWith('status.'))?.[1]
    );
}
function onPerPageChange(perPage: number): void {
    router.get(
        listIndex.value().url,
        {
            per_page: perPage,
            ...(props.filters.search ? { search: props.filters.search } : {}),
            ...(props.filters.status.length
                ? { status: props.filters.status }
                : {}),
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="title" />
    <StoreAdminLayout>
        <PageHeader :title="title">
            <div class="flex flex-wrap items-center gap-2">
                <AddResourceLink
                    v-if="access.can('add_order')"
                    :href="shippingCreate()"
                    :label="t('store.trips.index.createOrder')"
                />
                <AddResourceLink
                    v-if="access.can('add_trip')"
                    :href="tripsCreate()"
                    :inertia="false"
                    :label="t('store.trips.index.createTrip')"
                />
            </div>
        </PageHeader>
        <Form
            :action="listIndex()"
            #default="{ errors, processing }"
            class="mb-4 flex flex-wrap items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="filters.per_page" />
            <FormField
                id="search"
                :label="t('store.trips.index.search')"
                :value="filters.search"
                :error="errors.search"
                maxlength="191"
            />
            <fieldset>
                <legend :class="fieldLabelClass">
                    {{ t('store.trips.index.statusFilter') }}
                </legend>
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="status in statuses"
                        :key="status"
                        class="flex cursor-pointer items-center gap-1.5 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="status[]"
                            :value="status"
                            :checked="filters.status.includes(status)"
                            class="cursor-pointer"
                        />{{ t(`store.trips.status.${status}`) }}
                    </label>
                </div>
                <FieldError :message="statusError(errors)" />
            </fieldset>
            <div>
                <SubmitButton
                    :label="t('store.trips.index.apply')"
                    :processing-label="t('store.trips.index.applying')"
                    :processing="processing"
                />
            </div>
        </Form>
        <Form
            v-if="access.can('delete_trip')"
            :action="bulkDestroy(listQuery)"
            :on-before="
                () =>
                    selected.length > 0 &&
                    confirmDelete(
                        t('store.trips.index.selection', {
                            count: selected.length,
                        }),
                    )
            "
            #default="{ errors, processing }"
            class="mb-4 max-w-sm"
            @success="selected = []"
        >
            <input
                v-for="id in selected"
                :key="id"
                type="hidden"
                name="id[]"
                :value="id"
            />
            <SubmitButton
                :label="t('store.trips.index.deleteSelection')"
                :processing-label="t('store.trips.index.deleting')"
                :processing="processing"
                :disabled="processing || selected.length === 0"
            />
            <FieldError :message="firstError(errors)" />
        </Form>
        <DataTable
            :columns="[
                ...(access.can('delete_trip')
                    ? [t('store.trips.index.select')]
                    : []),
                t('store.trips.index.code'),
                t('store.trips.index.driver'),
                t('store.trips.index.truck'),
                t('store.trips.index.dueDate'),
                t('store.trips.index.status'),
                t('store.trips.index.startedAt'),
                t('store.trips.index.closedAt'),
                t('store.trips.index.distance'),
                t('store.trips.index.duration'),
                t('store.trips.index.notes'),
                t('store.trips.index.actions'),
            ]"
            :rows="trips.data"
            :empty-message="t('store.trips.index.empty')"
        >
            <template #row="{ row: trip }">
                <td v-if="access.can('delete_trip')" class="px-4 py-2">
                    <input
                        v-model="selected"
                        type="checkbox"
                        :value="trip.id"
                        :aria-label="
                            t('store.trips.index.selectTrip', {
                                code: trip.code,
                            })
                        "
                        class="cursor-pointer"
                    />
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <a
                        :href="tripsShow.url(trip)"
                        class="font-medium text-gray-900 underline dark:text-neutral-100"
                        >{{ trip.code }}</a
                    >
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        v-if="trip.driver && access.can('edit_driver')"
                        :href="driverEdit(trip.driver)"
                        >{{ trip.driver.name }}</TableLink
                    >
                    <template v-else>{{
                        trip.driver?.name ?? t('store.trips.index.notAvailable')
                    }}</template>
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        v-if="trip.truck && access.can('edit_truck')"
                        :href="truckEdit(trip.truck)"
                        >{{
                            t('store.trips.index.truckLabel', {
                                id: trip.truck.id,
                                name: trip.truck.name,
                            })
                        }}</TableLink
                    >
                    <template v-else>{{
                        trip.truck
                            ? t('store.trips.index.truckLabel', {
                                  id: trip.truck.id,
                                  name: trip.truck.name,
                              })
                            : t('store.trips.index.notAvailable')
                    }}</template>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">{{ trip.due_date }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <span>{{
                            t(`store.trips.status.${trip.status}`)
                        }}</span>
                        <button
                            v-if="access.can('edit_trip')"
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded p-1 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-neutral-800"
                            :aria-label="
                                t('store.trips.index.updateStatusFor', {
                                    code: trip.code,
                                })
                            "
                            :title="
                                t('store.trips.index.updateStatusFor', {
                                    code: trip.code,
                                })
                            "
                            @click="openStatus(trip)"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ trip.started_at ? formatDateTime(trip.started_at) : '' }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ trip.closed_at ? formatDateTime(trip.closed_at) : '' }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{
                        t('store.trips.index.km', {
                            distance: kilometres(trip.distance),
                        })
                    }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ formatDuration(trip.duration ?? 0) }}
                </td>
                <td class="px-4 py-2">
                    <template v-if="access.can('edit_trip')">
                        <textarea
                            :id="`trip-notes-${trip.id}`"
                            :value="trip.notes ?? ''"
                            :aria-label="
                                t('store.trips.index.notesFor', {
                                    code: trip.code,
                                })
                            "
                            maxlength="255"
                            rows="2"
                            :class="[plainFieldInputClass, 'min-w-40']"
                            @change="saveNotes(trip, $event)"
                        />
                        <template v-if="notesTripId === trip.id">
                            <FieldError
                                :message="firstError(notesForm.errors)"
                            />
                            <p
                                v-if="savedNotesTripId === trip.id"
                                role="status"
                                class="mt-1 text-xs text-green-700 dark:text-green-400"
                            >
                                {{ t('store.trips.index.notesSaved') }}
                            </p>
                        </template>
                    </template>
                    <span v-else class="whitespace-pre-line">{{
                        trip.notes
                    }}</span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_trip')"
                            :href="tripsEdit(trip)"
                            :inertia="false"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.trips.index.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_trip')"
                            :href="destroy(trip, listQuery)"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="
                                () =>
                                    confirmDelete(
                                        t('store.trips.index.trip', {
                                            code: trip.code,
                                        }),
                                    )
                            "
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.trips.index.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <label
            v-if="access.can('delete_trip') && trips.data.length"
            class="mt-3 flex cursor-pointer items-center gap-2"
        >
            <input
                type="checkbox"
                :checked="allSelected"
                class="cursor-pointer"
                @change="selectAll(($event.target as HTMLInputElement).checked)"
            />{{ t('store.trips.index.selectAll') }}
        </label>
        <Pagination
            :links="trips.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
        <StoreTripStatusDialog
            v-if="access.can('edit_trip')"
            v-model:open="statusOpen"
            :trip="statusTrip"
            :statuses="statuses"
            :list="listType"
        />
    </StoreAdminLayout>
</template>
