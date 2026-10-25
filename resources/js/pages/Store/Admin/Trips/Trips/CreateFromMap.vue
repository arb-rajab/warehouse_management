<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    CircleCheck,
    Trash2,
    Truck as TruckIcon,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { show as tripsShow } from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import { destroy } from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import { store as storeFromMap } from '@/actions/App/Http/Controllers/Store/Admin/TripMapController';
import { markDelivered } from '@/actions/App/Http/Controllers/Store/Admin/TripOrderController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import PageHeader from '@/components/PageHeader.vue';
import StoreTripMap from '@/components/StoreTripMap.vue';
import type { StoreTripMapPoint } from '@/components/StoreTripMap.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { pillLinkClass, primaryPillVariantClass } from '@/lib/actionLink';
import { confirmDelete, confirmDeliveryStatus } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { firstError, usePageError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import { tripCheckpointLabel } from '@/lib/storeTrip';
import type { StoreTripMapOrder, StoreTripMapTruck } from '@/types/store';
import type { TruckPhoto } from '@/types/storeTruck';

/**
 * The trip map picker, in place of the Blade `create-from-map` page
 * (store-backoffice-migration.md §30): the orders out for delivery on a
 * Leaflet map, where a marker click selects or drops an order; the selected
 * orders as an ordered list of stops that can be moved and removed, as the
 * trip form's stops are (§25); and the trucks, each assigning the stops to a
 * new trip or, for a busy truck, to its latest open trip.
 */
const props = defineProps<{
    orders: StoreTripMapOrder[];
    orderTrips: Record<number, number>;
    availableTrucks: StoreTripMapTruck[];
    busyTrucks: StoreTripMapTruck[];
    photos: TruckPhoto[];
}>();
const access = useStoreAccess();
const tripError = usePageError('trip_id');
const deliveryError = usePageError('delivery_status');
const selected = ref<number[]>([]);
const assignForm = useForm({
    truck_id: 0,
    trip_id: null as number | null,
    markers: [] as { order_id: number }[],
});
const assigningTruckId = ref<number | null>(null);

const points = computed<StoreTripMapPoint[]>(() =>
    props.orders.map((order) => ({
        id: order.id,
        latitude: order.latitude,
        longitude: order.longitude,
        label: orderLabel(order),
    })),
);
const stops = computed(() =>
    selected.value
        .map((id) => props.orders.find((order) => order.id === id))
        .filter((order): order is StoreTripMapOrder => order !== undefined),
);
/** A reload (after an assignment or a delivery) drops orders that left the map. */
watch(
    () => props.orders,
    (orders) => {
        selected.value = selected.value.filter((id) =>
            orders.some((order) => order.id === id),
        );
    },
);

function orderLabel(order: StoreTripMapOrder): string {
    return tripCheckpointLabel('delivery', order.code, order.customer.name);
}
function toggle(id: number): void {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((selectedId) => selectedId !== id)
        : [...selected.value, id];
}
function move(index: number, offset: number): void {
    const next = [...selected.value];
    [next[index], next[index + offset]] = [next[index + offset], next[index]];
    selected.value = next;
}
function remove(index: number): void {
    selected.value = selected.value.filter((_, position) => position !== index);
}
/** A busy truck's stops go to its latest open trip, which needs `edit_trip`. */
function canAssign(truck: StoreTripMapTruck): boolean {
    return truck.trip === null || access.value.can('edit_trip');
}
function assign(truck: StoreTripMapTruck): void {
    assigningTruckId.value = truck.id;
    assignForm.truck_id = truck.id;
    assignForm.trip_id = truck.trip?.id ?? null;
    assignForm.markers = selected.value.map((id) => ({ order_id: id }));
    assignForm.clearErrors();
    assignForm.submit(storeFromMap(), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
        },
        onFinish: () => {
            assigningTruckId.value = null;
        },
    });
}
function photoUrl(truck: StoreTripMapTruck): string | null {
    return props.photos.find((photo) => photo.id === truck.photo)?.url ?? null;
}
function hasStops(truck: StoreTripMapTruck): boolean {
    return truck.open_trips.some((trip) => trip.stops.length > 0);
}
</script>

<template>
    <Head :title="t('store.trips.map.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.trips.map.title')" />
        <p class="mb-4 text-sm text-gray-600 dark:text-neutral-400">
            {{ t('store.trips.map.help') }}
        </p>
        <ActionErrorBanner
            :message="
                firstError(assignForm.errors) || tripError || deliveryError
            "
        />
        <div class="mb-8 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <StoreTripMap
                    :points="points"
                    :selected="selected"
                    @toggle="toggle"
                />
                <p
                    v-if="orders.length === 0"
                    class="mt-2 text-sm text-gray-600 dark:text-neutral-400"
                >
                    {{ t('store.trips.map.noOrders') }}
                </p>
            </div>
            <section aria-labelledby="trip-map-stops">
                <h2 id="trip-map-stops" class="mb-3 text-lg font-semibold">
                    {{ t('store.trips.map.stops', { count: stops.length }) }}
                </h2>
                <p
                    v-if="stops.length === 0"
                    class="text-sm text-gray-600 dark:text-neutral-400"
                >
                    {{ t('store.trips.map.noStops') }}
                </p>
                <ol v-else class="space-y-3">
                    <li
                        v-for="(order, index) in stops"
                        :key="order.id"
                        class="rounded-md border border-gray-200 p-3 text-sm dark:border-neutral-700"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-medium">
                                {{
                                    t('store.trips.form.position', {
                                        position: index + 1,
                                    })
                                }}
                                · {{ orderLabel(order) }}
                            </p>
                            <div class="flex shrink-0 gap-1">
                                <button
                                    type="button"
                                    class="cursor-pointer rounded p-1.5 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-neutral-800"
                                    :disabled="index === 0"
                                    :aria-label="
                                        t('store.trips.form.moveUp', {
                                            position: index + 1,
                                        })
                                    "
                                    :title="
                                        t('store.trips.form.moveUp', {
                                            position: index + 1,
                                        })
                                    "
                                    @click="move(index, -1)"
                                >
                                    <ArrowUp class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded p-1.5 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-neutral-800"
                                    :disabled="index === stops.length - 1"
                                    :aria-label="
                                        t('store.trips.form.moveDown', {
                                            position: index + 1,
                                        })
                                    "
                                    :title="
                                        t('store.trips.form.moveDown', {
                                            position: index + 1,
                                        })
                                    "
                                    @click="move(index, 1)"
                                >
                                    <ArrowDown class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded p-1.5 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-neutral-800"
                                    :aria-label="
                                        t('store.trips.form.remove', {
                                            position: index + 1,
                                        })
                                    "
                                    :title="
                                        t('store.trips.form.remove', {
                                            position: index + 1,
                                        })
                                    "
                                    @click="remove(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <dl
                            class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1"
                        >
                            <dt class="text-gray-500 dark:text-neutral-400">
                                {{ t('store.trips.map.customerId') }}
                            </dt>
                            <dd>{{ order.customer.id }}</dd>
                            <dt class="text-gray-500 dark:text-neutral-400">
                                {{ t('store.trips.map.postalCode') }}
                            </dt>
                            <dd>{{ order.postal_code || '-' }}</dd>
                            <dt class="text-gray-500 dark:text-neutral-400">
                                {{ t('store.trips.map.phone') }}
                            </dt>
                            <dd>{{ order.customer.phone || '-' }}</dd>
                            <dt class="text-gray-500 dark:text-neutral-400">
                                {{ t('store.trips.map.address') }}
                            </dt>
                            <dd>{{ order.address || '-' }}</dd>
                            <dt class="text-gray-500 dark:text-neutral-400">
                                {{ t('store.trips.map.accountingId') }}
                            </dt>
                            <dd>{{ order.customer.accounting_id || '-' }}</dd>
                        </dl>
                        <div
                            v-if="
                                access.can('edit_trip') && orderTrips[order.id]
                            "
                            class="mt-2"
                        >
                            <TableActionLink
                                :href="
                                    markDelivered(order, {
                                        query: {
                                            return_to: 'map',
                                            trip_id: orderTrips[order.id],
                                        },
                                    })
                                "
                                method="put"
                                as="button"
                                preserve-scroll
                                :on-before="
                                    () =>
                                        confirmDeliveryStatus(
                                            t('store.trips.orders.order', {
                                                code: order.code,
                                            }),
                                            t('store.orders.status.delivered'),
                                        )
                                "
                                ><CircleCheck class="h-3.5 w-3.5" />{{
                                    t('store.trips.orders.markDelivered')
                                }}</TableActionLink
                            >
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <section aria-labelledby="trip-map-free-trucks" class="mb-8">
            <h2 id="trip-map-free-trucks" class="mb-1 text-lg font-semibold">
                {{ t('store.trips.map.freeTrucks') }}
            </h2>
            <p
                v-if="stops.length === 0"
                class="mb-3 text-sm text-gray-600 dark:text-neutral-400"
            >
                {{ t('store.trips.map.selectFirst') }}
            </p>
            <p
                v-if="availableTrucks.length === 0"
                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
            >
                {{ t('store.trips.map.noFreeTrucks') }}
            </p>
            <ul
                v-else
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <li
                    v-for="truck in availableTrucks"
                    :key="truck.id"
                    class="flex flex-col rounded-md border border-gray-200 p-3 text-sm dark:border-neutral-700"
                >
                    <img
                        v-if="photoUrl(truck)"
                        :src="photoUrl(truck)!"
                        :alt="
                            t('store.trips.map.photoOf', { name: truck.name })
                        "
                        width="150"
                        height="150"
                        class="mb-2 aspect-square w-full rounded object-cover"
                    />
                    <TruckIcon
                        v-else
                        class="mb-2 h-12 w-12 text-gray-400 dark:text-neutral-500"
                        aria-hidden="true"
                    />
                    <h3 class="font-semibold">{{ truck.name }}</h3>
                    <p class="text-gray-600 dark:text-neutral-400">
                        {{
                            t('store.trips.map.maxPallets', {
                                count: truck.max_pallets_number ?? '-',
                            })
                        }}
                    </p>
                    <button
                        v-if="canAssign(truck)"
                        type="button"
                        :class="[pillLinkClass, primaryPillVariantClass]"
                        class="mt-3 cursor-pointer self-start disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="stops.length === 0 || assignForm.processing"
                        @click="assign(truck)"
                    >
                        {{
                            assigningTruckId === truck.id
                                ? t('store.trips.map.assigning')
                                : t('store.trips.map.assign', {
                                      name: truck.name,
                                  })
                        }}
                    </button>
                </li>
            </ul>
        </section>

        <section aria-labelledby="trip-map-busy-trucks">
            <h2 id="trip-map-busy-trucks" class="mb-3 text-lg font-semibold">
                {{ t('store.trips.map.busyTrucks') }}
            </h2>
            <p
                v-if="busyTrucks.length === 0"
                class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300"
            >
                {{ t('store.trips.map.allFree') }}
            </p>
            <ul
                v-else
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <li
                    v-for="truck in busyTrucks"
                    :key="truck.id"
                    class="flex flex-col rounded-md border border-gray-200 p-3 text-sm dark:border-neutral-700"
                >
                    <img
                        v-if="photoUrl(truck)"
                        :src="photoUrl(truck)!"
                        :alt="
                            t('store.trips.map.photoOf', { name: truck.name })
                        "
                        width="150"
                        height="150"
                        class="mb-2 aspect-square w-full rounded object-cover"
                    />
                    <TruckIcon
                        v-else
                        class="mb-2 h-12 w-12 text-gray-400 dark:text-neutral-500"
                        aria-hidden="true"
                    />
                    <h3 class="font-semibold">
                        <TableLink
                            v-if="truck.trip"
                            :href="tripsShow(truck.trip)"
                            >{{ truck.name }}</TableLink
                        >
                        <template v-else>{{ truck.name }}</template>
                    </h3>
                    <template v-if="truck.trip && hasStops(truck)">
                        <p class="mb-2 text-gray-600 dark:text-neutral-400">
                            {{
                                truck.trip.driver?.name ??
                                t('store.trips.index.notAvailable')
                            }}
                        </p>
                        <div
                            v-for="trip in truck.open_trips"
                            :key="trip.id"
                            class="mb-2"
                        >
                            <ul class="space-y-1">
                                <li v-for="stop in trip.stops" :key="stop.id">
                                    <span
                                        class="block text-xs text-gray-500 dark:text-neutral-400"
                                        >{{
                                            t('store.trips.map.nextStop')
                                        }}</span
                                    >
                                    <TableLink
                                        v-if="
                                            stop.customer &&
                                            access.can('edit_customer')
                                        "
                                        :href="customerEdit(stop.customer)"
                                        >{{
                                            stop.customer.name ??
                                            t(
                                                'store.trips.form.unknownCustomer',
                                            )
                                        }}</TableLink
                                    >
                                    <span v-else class="font-medium">{{
                                        stop.customer?.name ??
                                        t('store.trips.form.unknownCustomer')
                                    }}</span>
                                </li>
                            </ul>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-neutral-400"
                            >
                                {{
                                    t('store.trips.map.checkpointsCount', {
                                        code: trip.code,
                                        count: trip.stops.length,
                                    })
                                }}
                            </p>
                        </div>
                    </template>
                    <p v-else class="text-gray-600 dark:text-neutral-400">
                        {{ t('store.trips.map.noTripInformation') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="canAssign(truck)"
                            type="button"
                            :class="[pillLinkClass, primaryPillVariantClass]"
                            class="cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                stops.length === 0 || assignForm.processing
                            "
                            @click="assign(truck)"
                        >
                            {{
                                assigningTruckId === truck.id
                                    ? t('store.trips.map.assigning')
                                    : t(
                                          truck.trip
                                              ? 'store.trips.map.addToTrip'
                                              : 'store.trips.map.assign',
                                          { name: truck.name },
                                      )
                            }}
                        </button>
                        <TableActionLink
                            v-if="truck.trip && access.can('delete_trip')"
                            :href="
                                destroy(truck.trip, {
                                    query: { list: 'map' },
                                })
                            "
                            method="delete"
                            as="button"
                            variant="danger"
                            preserve-scroll
                            :on-before="
                                () =>
                                    confirmDelete(
                                        t('store.trips.index.trip', {
                                            code: truck.trip!.code,
                                        }),
                                    )
                            "
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.trips.map.deleteTrip')
                            }}</TableActionLink
                        >
                    </div>
                </li>
            </ul>
        </section>
    </StoreAdminLayout>
</template>
