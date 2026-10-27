<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Printer } from '@lucide/vue';
import { ref } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { edit as driverEdit } from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { show as orderShow } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    printCmr,
    updateNotes,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import { edit as tripsEdit } from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import { edit as truckEdit } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import PageHeader from '@/components/PageHeader.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { pillLinkClass, primaryPillVariantClass } from '@/lib/actionLink';
import { confirmDeliveryStatus } from '@/lib/confirm';
import { currentLocaleTag, formatDateTime, formatDuration } from '@/lib/date';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError, usePageError } from '@/lib/pageErrors';
import { provideRequiredFields } from '@/lib/requiredFields';
import { useStoreAccess } from '@/lib/storeAccess';
import { tripCheckpointLabel } from '@/lib/storeTrip';
import type {
    StoreTrip,
    StoreTripCheckpointRow,
    StoreTripCheckpointStatus,
} from '@/types/store';
import type { TruckPhoto } from '@/types/storeTruck';

/**
 * A trip's detail, in place of the Blade checkpoints page: the trip and its
 * checkpoints in their order, with the notes and status saved per row. The
 * Blade page's Leaflet map is not carried over (map decision, §5).
 */
const props = defineProps<{
    trip: StoreTrip;
    checkpoints: StoreTripCheckpointRow[];
    photos: TruckPhoto[];
    statuses: StoreTripCheckpointStatus[];
}>();
const access = useStoreAccess();
const cmrError = usePageError('cmr');
/** Every row action returns to this page with its query. */
const samePage = { mergeQuery: {} };
const notesForm = useForm({ notes: '', trip_id: props.trip.id });
const notesCheckpointId = ref<number | null>(null);
const savedNotesCheckpointId = ref<number | null>(null);
const statusForm = useForm({
    status: '' as StoreTripCheckpointStatus | '',
    trip_id: props.trip.id,
});
const statusCheckpointId = ref<number | null>(null);

function stop(checkpoint: StoreTripCheckpointRow): string {
    return t('store.trips.show.stop', {
        position: checkpoint.position ?? '-',
    });
}
function statusLabel(status: string): string {
    return t(`store.trips.show.statuses.${status}`);
}
/** Saves a row's notes when the field changes, as the Blade page did. */
function saveNotes(checkpoint: StoreTripCheckpointRow, event: Event): void {
    notesCheckpointId.value = checkpoint.id;
    savedNotesCheckpointId.value = null;
    notesForm.notes = (event.target as HTMLTextAreaElement).value;
    notesForm.clearErrors();
    notesForm.submit(updateNotes(checkpoint, samePage), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            savedNotesCheckpointId.value = checkpoint.id;
        },
    });
}
/**
 * Saves a row's status once the change is confirmed; a refused confirmation
 * puts the select back to the stored status.
 */
function saveStatus(checkpoint: StoreTripCheckpointRow, event: Event): void {
    const select = event.target as HTMLSelectElement;
    const status = select.value as StoreTripCheckpointStatus;

    if (!confirmDeliveryStatus(stop(checkpoint), statusLabel(status))) {
        select.value = checkpoint.status;

        return;
    }

    statusCheckpointId.value = checkpoint.id;
    statusForm.status = status;
    statusForm.clearErrors();
    statusForm.submit(updateStatus(checkpoint, samePage), {
        preserveScroll: true,
        onError: () => {
            select.value = checkpoint.status;
        },
    });
}
function photoUrls(checkpoint: StoreTripCheckpointRow): string[] {
    return checkpoint.photos
        .map((id) => props.photos.find((photo) => photo.id === id)?.url)
        .filter((url): url is string => typeof url === 'string');
}
/** Metres as kilometres to two places, as the trip lists show them. */
function kilometres(metres: number | null): string {
    return new Intl.NumberFormat(currentLocaleTag(), {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format((metres ?? 0) / 1000);
}
function truckLabel(truck: { id: number; name: string }): string {
    return t('store.trips.index.truckLabel', {
        id: truck.id,
        name: truck.name,
    });
}
function print(): void {
    window.print();
}
const hasRequiredFields = provideRequiredFields();
</script>

<template>
    <Head :title="t('store.trips.show.title', { code: trip.code })" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.trips.show.title', { code: trip.code })">
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <TableActionLink
                    v-if="access.can('edit_trip') && trip.status !== 'closed'"
                    :href="tripsEdit(trip)"
                    ><Pencil class="h-3.5 w-3.5" />{{
                        t('store.trips.show.edit')
                    }}</TableActionLink
                >
                <button
                    type="button"
                    :class="[pillLinkClass, primaryPillVariantClass]"
                    class="cursor-pointer"
                    @click="print"
                >
                    <Printer class="h-3.5 w-3.5" />{{
                        t('store.trips.show.print')
                    }}
                </button>
            </div>
        </PageHeader>
        <ActionErrorBanner :message="cmrError" />
        <dl
            class="mb-6 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4"
        >
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.status') }}
                </dt>
                <dd class="font-medium">
                    {{ t(`store.trips.status.${trip.status}`) }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.dueDate') }}
                </dt>
                <dd>
                    {{ trip.due_date ?? t('store.trips.index.notAvailable') }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.driver') }}
                </dt>
                <dd>
                    <TableLink
                        v-if="trip.driver && access.can('edit_driver')"
                        :href="driverEdit(trip.driver)"
                        >{{ trip.driver.name }}</TableLink
                    >
                    <template v-else>{{
                        trip.driver?.name ?? t('store.trips.index.notAvailable')
                    }}</template>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.truck') }}
                </dt>
                <dd>
                    <TableLink
                        v-if="trip.truck && access.can('edit_truck')"
                        :href="truckEdit(trip.truck)"
                        >{{ truckLabel(trip.truck) }}</TableLink
                    >
                    <template v-else>{{
                        trip.truck
                            ? truckLabel(trip.truck)
                            : t('store.trips.index.notAvailable')
                    }}</template>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.startedAt') }}
                </dt>
                <dd>
                    {{
                        trip.started_at
                            ? formatDateTime(trip.started_at)
                            : t('store.trips.index.notAvailable')
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.closedAt') }}
                </dt>
                <dd>
                    {{
                        trip.closed_at
                            ? formatDateTime(trip.closed_at)
                            : t('store.trips.index.notAvailable')
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.distance') }}
                </dt>
                <dd>
                    {{
                        t('store.trips.index.km', {
                            distance: kilometres(trip.distance),
                        })
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.duration') }}
                </dt>
                <dd>{{ formatDuration(trip.duration ?? 0) }}</dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <dt class="text-gray-500 dark:text-neutral-400">
                    {{ t('store.trips.index.notes') }}
                </dt>
                <dd class="whitespace-pre-line">
                    {{ trip.notes || t('store.trips.index.notAvailable') }}
                </dd>
            </div>
        </dl>
        <h2 class="mb-3 text-lg font-semibold">
            {{ t('store.trips.show.checkpoints') }}
        </h2>
        <RequiredLegend v-if="hasRequiredFields && access.can('edit_trip')" />
        <DataTable
            :columns="[
                t('store.trips.show.position'),
                t('store.trips.show.checkpoint'),
                t('store.trips.show.customer'),
                t('store.trips.show.representative'),
                t('store.trips.show.address'),
                t('store.trips.show.status'),
                t('store.trips.show.arrivedAt'),
                t('store.trips.show.eta'),
                t('store.trips.show.notes'),
                t('store.trips.show.phone'),
                t('store.trips.show.products'),
                t('store.trips.show.company'),
                t('store.trips.show.amount'),
                t('store.trips.show.invoiceNumber'),
                t('store.trips.show.photos'),
                t('store.trips.show.cmr'),
            ]"
            :rows="checkpoints"
            :empty-message="t('store.trips.show.empty')"
        >
            <template #row="{ row: checkpoint }">
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ checkpoint.position ?? '-' }}
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        v-if="
                            checkpoint.order && access.can('view_order_details')
                        "
                        :href="
                            orderShow['/temp/admin/orders/{order}/show'](
                                checkpoint.order.id,
                            )
                        "
                        >{{
                            tripCheckpointLabel(
                                checkpoint.type,
                                checkpoint.order.code,
                                checkpoint.customer?.name ?? null,
                            )
                        }}</TableLink
                    >
                    <template v-else>{{
                        tripCheckpointLabel(
                            checkpoint.type,
                            checkpoint.order?.code ?? null,
                            checkpoint.customer?.name ?? null,
                        )
                    }}</template>
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        v-if="
                            checkpoint.customer && access.can('edit_customer')
                        "
                        :href="customerEdit(checkpoint.customer)"
                        >{{
                            checkpoint.customer.name ??
                            t('store.trips.form.unknownCustomer')
                        }}</TableLink
                    >
                    <template v-else>{{
                        checkpoint.customer?.name ?? '-'
                    }}</template>
                </td>
                <td class="px-4 py-2">
                    {{ checkpoint.representative?.name ?? '-' }}
                </td>
                <td class="px-4 py-2">
                    {{ checkpoint.customer?.address || '-' }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <template
                        v-if="
                            access.can('edit_trip') && trip.status !== 'closed'
                        "
                    >
                        <label
                            :for="`checkpoint-status-${checkpoint.id}`"
                            :class="fieldLabelClass"
                            >{{
                                t('store.trips.show.statusFor', {
                                    stop: stop(checkpoint),
                                })
                            }}<RequiredMark
                        /></label>
                        <select
                            :id="`checkpoint-status-${checkpoint.id}`"
                            :value="checkpoint.status"
                            required
                            :class="plainFieldInputClass"
                            @change="saveStatus(checkpoint, $event)"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ statusLabel(status) }}
                            </option>
                        </select>
                        <FieldError
                            v-if="statusCheckpointId === checkpoint.id"
                            :message="firstError(statusForm.errors)"
                        />
                    </template>
                    <template v-else>{{
                        statusLabel(checkpoint.status)
                    }}</template>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{
                        checkpoint.arrived_at
                            ? formatDateTime(checkpoint.arrived_at)
                            : ''
                    }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{
                        checkpoint.eta === null
                            ? '-'
                            : formatDuration(checkpoint.eta)
                    }}
                </td>
                <td class="px-4 py-2">
                    <template
                        v-if="
                            access.can('edit_trip') && trip.status !== 'closed'
                        "
                    >
                        <textarea
                            :id="`checkpoint-notes-${checkpoint.id}`"
                            :value="checkpoint.notes ?? ''"
                            :aria-label="
                                t('store.trips.show.notesFor', {
                                    stop: stop(checkpoint),
                                })
                            "
                            maxlength="1000"
                            rows="2"
                            :class="[plainFieldInputClass, 'min-w-40']"
                            @change="saveNotes(checkpoint, $event)"
                        />
                        <template v-if="notesCheckpointId === checkpoint.id">
                            <FieldError
                                :message="firstError(notesForm.errors)"
                            />
                            <p
                                v-if="savedNotesCheckpointId === checkpoint.id"
                                role="status"
                                class="mt-1 text-xs text-green-700 dark:text-green-400"
                            >
                                {{ t('store.trips.show.notesSaved') }}
                            </p>
                        </template>
                    </template>
                    <span v-else class="whitespace-pre-line">{{
                        checkpoint.notes
                    }}</span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ checkpoint.customer?.phone || '-' }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ checkpoint.order ? checkpoint.order.products : '-' }}
                </td>
                <td class="px-4 py-2">
                    {{ checkpoint.customer?.company_name || '-' }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ checkpoint.order?.amount ?? '-' }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{
                        checkpoint.order
                            ? (checkpoint.order.invoice_number ?? '')
                            : '-'
                    }}
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="photoUrls(checkpoint).length"
                        class="flex flex-wrap gap-1"
                    >
                        <img
                            v-for="url in photoUrls(checkpoint)"
                            :key="url"
                            :src="url"
                            :alt="
                                t('store.trips.show.photoOf', {
                                    stop: stop(checkpoint),
                                })
                            "
                            width="100"
                            height="100"
                            class="h-25 w-25 rounded object-cover"
                        />
                    </div>
                    <template v-else>-</template>
                </td>
                <td class="px-4 py-2">
                    <TableActionLink
                        v-if="checkpoint.has_cmr"
                        :href="printCmr(checkpoint)"
                        :inertia="false"
                        class="print:hidden"
                        ><Printer class="h-3.5 w-3.5" />{{
                            t('store.trips.show.printCmr')
                        }}</TableActionLink
                    >
                    <template v-else>-</template>
                </td>
            </template>
        </DataTable>
    </StoreAdminLayout>
</template>
