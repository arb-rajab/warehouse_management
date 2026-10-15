<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { getAvailableVehicles } from '@/actions/App/Http/Controllers/Api/V2/Trip/DriverController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreReferenceOption,
    StoreTripCheckpointType,
    StoreTripForm,
    StoreTripOrderOption,
    StoreTripTruckOption,
    StoreTripVehicles,
} from '@/types/store';

const props = defineProps<{
    trip?: StoreTripForm;
    orders: StoreTripOrderOption[];
    customers: StoreReferenceOption[];
    errors: Record<string, string>;
}>();

/**
 * A checkpoint row. A stored row (`id` > 0) keeps its order or customer, so
 * it shows them as text; only its notes and position change.
 */
interface CheckpointRow {
    key: number;
    id: number;
    type: StoreTripCheckpointType;
    relatedId: string;
    notes: string;
    orderCode: string | null;
    customer: string | null;
}

let nextKey = 0;
const rows = ref<CheckpointRow[]>(
    (props.trip?.checkpoints ?? []).map((checkpoint) => ({
        key: nextKey++,
        id: checkpoint.id,
        type: checkpoint.type,
        relatedId: String(checkpoint.related_id),
        notes: checkpoint.notes ?? '',
        orderCode: checkpoint.order_code,
        customer: checkpoint.customer,
    })),
);
const dueDate = ref(props.trip?.due_date ?? '');
const driverId = ref(props.trip?.driver_id ? String(props.trip.driver_id) : '');
const truckId = ref(props.trip?.truck_id ? String(props.trip.truck_id) : '');
const drivers = ref<StoreReferenceOption[]>([]);
const trucks = ref<StoreTripTruckOption[]>([]);
const vehiclesLoaded = ref(false);
const vehiclesFailed = ref(false);
const vehiclesRequest = useHttp<Record<string, never>, StoreTripVehicles>({});
let revision = 0;

/**
 * The drivers and trucks free on the due date (the trip's own included when
 * editing), from the endpoint the Blade forms used. A choice no longer
 * offered is cleared.
 */
async function loadVehicles(): Promise<void> {
    const current = ++revision;
    vehiclesFailed.value = false;

    if (!dueDate.value) {
        drivers.value = [];
        trucks.value = [];
        vehiclesLoaded.value = false;

        return;
    }

    try {
        const result = await vehiclesRequest.get(
            getAvailableVehicles['/temp/admin/trips/available-vehicles'].url({
                query: {
                    date: dueDate.value,
                    excluded_trip_id: props.trip?.id ?? 0,
                },
            }),
        );

        if (revision !== current) {
            return;
        }

        drivers.value = (result?.drivers ?? []).map(({ id, name }) => ({
            id,
            name,
        }));
        trucks.value = (result?.trucks ?? []).map(({ id, name, model }) => ({
            id,
            name,
            model,
        }));
        vehiclesFailed.value = !result;
    } catch {
        if (revision !== current) {
            return;
        }

        drivers.value = [];
        trucks.value = [];
        vehiclesFailed.value = true;
    }

    vehiclesLoaded.value = true;

    if (!drivers.value.some((driver) => String(driver.id) === driverId.value)) {
        driverId.value = '';
    }

    if (!trucks.value.some((truck) => String(truck.id) === truckId.value)) {
        truckId.value = '';
    }
}
onMounted(() => void loadVehicles());
function changeDueDate(event: Event): void {
    dueDate.value = (event.target as HTMLInputElement).value;
    void loadVehicles();
}

function addRow(type: StoreTripCheckpointType): void {
    rows.value.push({
        key: nextKey++,
        id: 0,
        type,
        relatedId: '',
        notes: '',
        orderCode: null,
        customer: null,
    });
}
function moveRow(index: number, offset: -1 | 1): void {
    const target = index + offset;

    if (target < 0 || target >= rows.value.length) {
        return;
    }

    const reordered = [...rows.value];
    [reordered[index], reordered[target]] = [
        reordered[target],
        reordered[index],
    ];
    rows.value = reordered;
}
function removeRow(index: number): void {
    rows.value.splice(index, 1);
}
function storedLabel(row: CheckpointRow): string {
    const customer = row.customer ?? t('store.trips.form.unknownCustomer');

    return row.type === 'delivery'
        ? t('store.trips.form.orderOption', {
              code: row.orderCode ?? row.relatedId,
              customer,
          })
        : customer;
}
function truckLabel(truck: StoreTripTruckOption): string {
    return truck.model ? `${truck.name} ${truck.model}` : truck.name;
}
/** The checkpoint list's own error, or the departure checkpoint's. */
function checkpointsError(): string | undefined {
    return props.errors.checkpoints_array ?? props.errors.departure;
}
function rowError(index: number): string | undefined {
    return Object.entries(props.errors).find(([key]) =>
        key.startsWith(`checkpoints_array.${index}`),
    )?.[1];
}
function requireOneRow(event: Event): void {
    (event.target as HTMLInputElement).setCustomValidity(
        t('store.trips.form.checkpointsRequired'),
    );
}
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <FormField
                    id="due_date"
                    type="date"
                    :label="t('store.trips.form.dueDate')"
                    :value="trip?.due_date ?? ''"
                    :error="errors.due_date"
                    required
                    @change="changeDueDate"
                />
                <p
                    v-if="
                        vehiclesLoaded &&
                        (vehiclesFailed || !drivers.length || !trucks.length)
                    "
                    role="status"
                    class="mt-1 text-sm text-amber-700 dark:text-amber-400"
                >
                    {{ t('store.trips.form.noVehicles') }}
                </p>
            </div>
            <div>
                <label for="driver_id" :class="fieldLabelClass">{{
                    t('store.trips.form.driver')
                }}</label>
                <select
                    id="driver_id"
                    v-model="driverId"
                    name="driver_id"
                    :class="[plainFieldInputClass, 'cursor-pointer']"
                >
                    <option value="">
                        {{ t('store.trips.form.selectDriver') }}
                    </option>
                    <option
                        v-for="driver in drivers"
                        :key="driver.id"
                        :value="String(driver.id)"
                    >
                        {{ driver.name }}
                    </option>
                </select>
                <p
                    v-if="vehiclesLoaded && !vehiclesFailed && !drivers.length"
                    class="mt-1 text-sm text-gray-600 dark:text-neutral-400"
                >
                    {{ t('store.trips.form.noDrivers') }}
                </p>
                <FieldError :message="errors.driver_id" />
            </div>
            <div>
                <label for="truck_id" :class="fieldLabelClass">{{
                    t('store.trips.form.truck')
                }}</label>
                <select
                    id="truck_id"
                    v-model="truckId"
                    name="truck_id"
                    required
                    :class="[plainFieldInputClass, 'cursor-pointer']"
                >
                    <option value="">
                        {{ t('store.trips.form.selectTruck') }}
                    </option>
                    <option
                        v-for="truck in trucks"
                        :key="truck.id"
                        :value="String(truck.id)"
                    >
                        {{ truckLabel(truck) }}
                    </option>
                </select>
                <p
                    v-if="vehiclesLoaded && !vehiclesFailed && !trucks.length"
                    class="mt-1 text-sm text-gray-600 dark:text-neutral-400"
                >
                    {{ t('store.trips.form.noTrucks') }}
                </p>
                <FieldError :message="errors.truck_id" />
            </div>
        </div>
        <div>
            <label for="notes" :class="fieldLabelClass">{{
                t('store.trips.form.notes')
            }}</label>
            <textarea
                id="notes"
                name="notes"
                :default-value.camel="trip?.notes ?? ''"
                maxlength="255"
                rows="3"
                :class="plainFieldInputClass"
            />
            <FieldError :message="errors.notes" />
        </div>
        <fieldset class="space-y-3">
            <legend class="text-base font-semibold">
                {{ t('store.trips.form.checkpoints') }}
            </legend>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
                {{ t('store.trips.form.checkpointsHelp') }}
            </p>
            <input
                v-if="!rows.length"
                type="text"
                value=""
                required
                tabindex="-1"
                class="sr-only"
                :aria-label="t('store.trips.form.checkpointsRequired')"
                @invalid="requireOneRow"
            />
            <p
                v-if="!rows.length"
                class="text-sm text-gray-600 dark:text-neutral-400"
            >
                {{ t('store.trips.form.empty') }}
            </p>
            <FieldError :message="checkpointsError()" />
            <ol class="space-y-3">
                <li
                    v-for="(row, index) in rows"
                    :key="row.key"
                    data-testid="trip-checkpoint"
                    class="rounded-md border border-gray-200 p-3 dark:border-neutral-700"
                >
                    <input
                        type="hidden"
                        :name="`checkpoints_array[${index}][id]`"
                        :value="row.id"
                    />
                    <input
                        type="hidden"
                        :name="`checkpoints_array[${index}][checkpoint_type]`"
                        :value="row.type"
                    />
                    <div class="grid gap-3 md:grid-cols-[auto_1fr_1fr_auto]">
                        <div class="text-sm">
                            <span class="block font-medium">{{
                                t('store.trips.form.position', {
                                    position: index + 1,
                                })
                            }}</span>
                            <span class="text-gray-600 dark:text-neutral-400">{{
                                t(`store.trips.form.types.${row.type}`)
                            }}</span>
                        </div>
                        <div v-if="row.id > 0">
                            <span :class="fieldLabelClass">{{
                                t(
                                    row.type === 'delivery'
                                        ? 'store.trips.form.order'
                                        : 'store.trips.form.customer',
                                )
                            }}</span>
                            <input
                                type="hidden"
                                :name="`checkpoints_array[${index}][checkpoint_related_id]`"
                                :value="row.relatedId"
                            />
                            <p class="py-2 text-sm">{{ storedLabel(row) }}</p>
                        </div>
                        <div v-else>
                            <label
                                :for="`checkpoint-${row.key}-related`"
                                :class="fieldLabelClass"
                                >{{
                                    t(
                                        row.type === 'delivery'
                                            ? 'store.trips.form.order'
                                            : 'store.trips.form.customer',
                                    )
                                }}</label
                            >
                            <select
                                :id="`checkpoint-${row.key}-related`"
                                v-model="row.relatedId"
                                :name="`checkpoints_array[${index}][checkpoint_related_id]`"
                                required
                                :class="[
                                    plainFieldInputClass,
                                    'cursor-pointer',
                                ]"
                            >
                                <option value="">
                                    {{
                                        t(
                                            row.type === 'delivery'
                                                ? 'store.trips.form.selectOrder'
                                                : 'store.trips.form.selectCustomer',
                                        )
                                    }}
                                </option>
                                <template v-if="row.type === 'delivery'">
                                    <option
                                        v-for="order in orders"
                                        :key="order.id"
                                        :value="String(order.id)"
                                    >
                                        {{
                                            t('store.trips.form.orderOption', {
                                                code: order.code,
                                                customer:
                                                    order.customer ??
                                                    t(
                                                        'store.trips.form.unknownCustomer',
                                                    ),
                                            })
                                        }}
                                    </option>
                                </template>
                                <template v-else>
                                    <option
                                        v-for="customer in customers"
                                        :key="customer.id"
                                        :value="String(customer.id)"
                                    >
                                        {{ customer.name }}
                                    </option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label
                                :for="`checkpoint-${row.key}-notes`"
                                :class="fieldLabelClass"
                                >{{
                                    t('store.trips.form.checkpointNotes')
                                }}</label
                            >
                            <textarea
                                :id="`checkpoint-${row.key}-notes`"
                                v-model="row.notes"
                                :name="`checkpoints_array[${index}][checkpoint_notes]`"
                                maxlength="1000"
                                rows="2"
                                :class="plainFieldInputClass"
                            />
                        </div>
                        <div class="flex items-start gap-1">
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
                                @click="moveRow(index, -1)"
                            >
                                <ArrowUp class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded p-1.5 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-neutral-800"
                                :disabled="index === rows.length - 1"
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
                                @click="moveRow(index, 1)"
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
                                @click="removeRow(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <FieldError :message="rowError(index)" />
                </li>
            </ol>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    @click="addRow('delivery')"
                >
                    <Plus class="h-4 w-4" />{{ t('store.trips.form.addOrder') }}
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    @click="addRow('other')"
                >
                    <Plus class="h-4 w-4" />{{
                        t('store.trips.form.addCustomer')
                    }}
                </button>
            </div>
        </fieldset>
    </div>
</template>
