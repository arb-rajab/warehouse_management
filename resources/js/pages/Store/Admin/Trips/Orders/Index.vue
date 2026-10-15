<script setup lang="ts">
import { Form, Head, router, useForm } from '@inertiajs/vue3';
import { CircleCheck, Pencil, Printer } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { edit as driverEdit } from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { bulk_order_delete } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    edit as orderEdit,
    shippingCreate,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import {
    bulkUpdateDeliveryStatus,
    index as tripOrders,
    markDelivered,
    updateInvoiceNumber,
} from '@/actions/App/Http/Controllers/Store/Admin/TripOrderController';
import { edit as truckEdit } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import {
    print_cmr,
    show as tripsShow,
} from '@/actions/App/Http/Controllers/Trip/TripController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete, confirmDeliveryStatus } from '@/lib/confirm';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreTripOrder, StoreTripOrderFilters } from '@/types/store';
import type { TruckPhoto } from '@/types/storeTruck';

const props = defineProps<{
    orders: Paginated<StoreTripOrder>;
    photos: TruckPhoto[];
    filters: StoreTripOrderFilters;
    deliveryStatuses: string[];
    bulkStatuses: string[];
}>();
const access = useStoreAccess();
const canSelect = computed(
    () => access.value.can('edit_trip') || access.value.can('delete_order'),
);
/** Every row action returns to this list with its filters and page. */
const sameList = { mergeQuery: {} };
const selected = ref<number[]>([]);
const allSelected = computed(
    () =>
        props.orders.data.length > 0 &&
        props.orders.data.every((order) => selected.value.includes(order.id)),
);
watch(
    () => props.orders.data.map((order) => order.id).join(','),
    () => {
        selected.value = [];
    },
);
const invoiceForm = useForm({ invoice_number: '' });
const invoiceOrderId = ref<number | null>(null);
const savedInvoiceOrderId = ref<number | null>(null);

function selectAll(checked: boolean): void {
    selected.value = checked ? props.orders.data.map((order) => order.id) : [];
}
function statusLabel(status: string): string {
    return t(`store.orders.status.${status}`);
}
function selection(): string {
    return t('store.trips.orders.selection', { count: selected.value.length });
}
/** Saves a row's invoice number when the field changes, as the Blade list did. */
function saveInvoice(order: StoreTripOrder, event: Event): void {
    invoiceOrderId.value = order.id;
    savedInvoiceOrderId.value = null;
    invoiceForm.invoice_number = (event.target as HTMLInputElement).value;
    invoiceForm.clearErrors();
    invoiceForm.submit(updateInvoiceNumber(order, sameList), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            savedInvoiceOrderId.value = order.id;
        },
    });
}
function photoUrls(order: StoreTripOrder): string[] {
    return (order.checkpoint?.photos ?? [])
        .map((id) => props.photos.find((photo) => photo.id === id)?.url)
        .filter((url): url is string => typeof url === 'string');
}
function mapUrl(address: string): string {
    return `https://www.google.com/maps?q=${encodeURIComponent(address)}`;
}
function onPerPageChange(perPage: number): void {
    router.get(
        tripOrders().url,
        {
            per_page: perPage,
            ...Object.fromEntries(
                Object.entries(props.filters).filter(
                    ([key, value]) => key !== 'per_page' && value !== '',
                ),
            ),
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.trips.orders.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.trips.orders.title')">
            <AddResourceLink
                v-if="access.can('add_order')"
                :href="shippingCreate()"
                :label="t('store.trips.orders.createOrder')"
            />
        </PageHeader>
        <p
            v-if="access.can('edit_trip')"
            class="mb-4 text-sm text-gray-600 dark:text-neutral-400"
        >
            {{ t('store.trips.orders.invoiceHelp') }}
        </p>
        <Form
            :action="tripOrders()"
            #default="{ errors, processing }"
            class="mb-4 flex flex-wrap items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="filters.per_page" />
            <FormField
                id="search"
                :label="t('store.trips.orders.search')"
                :value="filters.search"
                :error="errors.search"
                maxlength="100"
            />
            <fieldset>
                <legend :class="fieldLabelClass">
                    {{ t('store.trips.orders.statusFilter') }}
                </legend>
                <div class="flex flex-wrap gap-3">
                    <label
                        class="flex cursor-pointer items-center gap-1.5 text-sm"
                    >
                        <input
                            type="radio"
                            name="delivery_status"
                            value=""
                            :checked="filters.delivery_status === ''"
                            class="cursor-pointer"
                        />{{ t('store.trips.orders.allStatuses') }}
                    </label>
                    <label
                        v-for="status in deliveryStatuses"
                        :key="status"
                        class="flex cursor-pointer items-center gap-1.5 text-sm"
                    >
                        <input
                            type="radio"
                            name="delivery_status"
                            :value="status"
                            :checked="filters.delivery_status === status"
                            class="cursor-pointer"
                        />{{ statusLabel(status) }}
                    </label>
                </div>
                <FieldError :message="errors.delivery_status" />
            </fieldset>
            <FormField
                id="from"
                type="date"
                :label="t('store.trips.orders.from')"
                :value="filters.from"
                :error="errors.from"
            />
            <FormField
                id="to"
                type="date"
                :label="t('store.trips.orders.to')"
                :value="filters.to"
                :error="errors.to"
            />
            <div>
                <SubmitButton
                    :label="t('store.trips.orders.apply')"
                    :processing-label="t('store.trips.orders.applying')"
                    :processing="processing"
                />
            </div>
        </Form>
        <div v-if="canSelect" class="mb-4 flex flex-wrap items-start gap-3">
            <template v-if="access.can('edit_trip')">
                <Form
                    v-for="status in bulkStatuses"
                    :key="status"
                    :action="bulkUpdateDeliveryStatus(sameList)"
                    :on-before="
                        () =>
                            selected.length > 0 &&
                            confirmDeliveryStatus(
                                selection(),
                                statusLabel(status),
                            )
                    "
                    #default="{ errors, processing }"
                    @success="selected = []"
                >
                    <input
                        type="hidden"
                        name="delivery_status"
                        :value="status"
                    />
                    <input
                        v-for="id in selected"
                        :key="id"
                        type="hidden"
                        name="id[]"
                        :value="id"
                    />
                    <SubmitButton
                        :label="
                            t('store.trips.orders.markSelection', {
                                status: statusLabel(status),
                            })
                        "
                        :processing-label="t('store.trips.orders.updating')"
                        :processing="processing"
                        :disabled="processing || selected.length === 0"
                    />
                    <FieldError :message="firstError(errors)" />
                </Form>
            </template>
            <Form
                v-if="access.can('delete_order')"
                :action="
                    bulk_order_delete({
                        mergeQuery: { return_to: 'trip_orders' },
                    })
                "
                :on-before="
                    () => selected.length > 0 && confirmDelete(selection())
                "
                #default="{ errors, processing }"
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
                    :label="t('store.trips.orders.deleteSelection')"
                    :processing-label="t('store.trips.orders.deleting')"
                    :processing="processing"
                    :disabled="processing || selected.length === 0"
                />
                <FieldError :message="firstError(errors)" />
            </Form>
        </div>
        <DataTable
            :columns="[
                ...(canSelect ? [t('store.trips.orders.select')] : []),
                t('store.trips.orders.code'),
                t('store.trips.orders.products'),
                t('store.trips.orders.otajerOrderId'),
                t('store.trips.orders.accountingId'),
                t('store.trips.orders.companyName'),
                t('store.trips.orders.orderFrom'),
                t('store.trips.orders.wmsStatus'),
                t('store.trips.orders.customer'),
                t('store.trips.orders.tripCode'),
                t('store.trips.orders.driver'),
                t('store.trips.orders.truck'),
                t('store.trips.orders.representative'),
                t('store.trips.orders.address'),
                t('store.trips.orders.country'),
                t('store.trips.orders.phone'),
                t('store.trips.orders.invoiceNumber'),
                t('store.trips.orders.customerNotes'),
                t('store.trips.orders.managerNotes'),
                t('store.trips.orders.amount'),
                t('store.trips.orders.deliveryStatus'),
                t('store.trips.orders.deliveryDate'),
                t('store.trips.orders.photos'),
                t('store.trips.orders.actions'),
            ]"
            :rows="orders.data"
            :empty-message="t('store.trips.orders.empty')"
        >
            <template #row="{ row: order }">
                <td v-if="canSelect" class="px-4 py-2">
                    <input
                        v-model="selected"
                        type="checkbox"
                        :value="order.id"
                        :aria-label="
                            t('store.trips.orders.selectOrder', {
                                code: order.code,
                            })
                        "
                        class="cursor-pointer"
                    />
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ order.code }}
                    <span
                        v-if="order.is_new"
                        class="ms-1 rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-800 dark:bg-sky-900 dark:text-sky-100"
                        >{{ t('store.trips.orders.new') }}</span
                    >
                </td>
                <td class="px-4 py-2">{{ order.details_count }}</td>
                <td class="px-4 py-2">{{ order.otajer_order_id ?? '' }}</td>
                <td class="px-4 py-2">
                    {{ order.customer?.accounting_id ?? '--' }}
                </td>
                <td class="px-4 py-2">
                    {{ order.customer?.company_name ?? '--' }}
                </td>
                <td class="px-4 py-2">{{ order.order_from ?? '--' }}</td>
                <td class="px-4 py-2">{{ order.wms_status ?? '' }}</td>
                <td class="px-4 py-2">
                    <template v-if="order.customer">
                        <TableLink
                            v-if="access.can('edit_customer')"
                            :href="customerEdit(order.customer)"
                            >{{ order.customer.name }}</TableLink
                        >
                        <template v-else>{{ order.customer.name }}</template>
                    </template>
                    <template v-else>{{
                        t('store.trips.orders.deletedAccount')
                    }}</template>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <a
                        v-if="order.trip"
                        :href="tripsShow.url(order.trip)"
                        class="font-medium text-gray-900 underline dark:text-neutral-100"
                        >{{ order.trip.code }}</a
                    >
                    <template v-else>-</template>
                </td>
                <td class="px-4 py-2">
                    <template v-if="order.trip?.driver">
                        <TableLink
                            v-if="access.can('edit_driver')"
                            :href="driverEdit(order.trip.driver)"
                            >{{ order.trip.driver.name }}</TableLink
                        >
                        <template v-else>{{ order.trip.driver.name }}</template>
                    </template>
                    <template v-else>-</template>
                </td>
                <td class="px-4 py-2">
                    <template v-if="order.trip?.truck">
                        <TableLink
                            v-if="access.can('edit_truck')"
                            :href="truckEdit(order.trip.truck)"
                            >{{ order.trip.truck.name }}</TableLink
                        >
                        <template v-else>{{ order.trip.truck.name }}</template>
                    </template>
                    <template v-else>-</template>
                </td>
                <td class="px-4 py-2">
                    <template v-if="order.by_rep">{{
                        order.representative?.name ??
                        t('store.trips.orders.deletedAccount')
                    }}</template>
                    <template v-else>{{
                        t('store.trips.orders.noRepresentative')
                    }}</template>
                </td>
                <td class="px-4 py-2">
                    <a
                        v-if="order.address"
                        :href="mapUrl(order.address)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="underline"
                        >{{ order.address }}</a
                    >
                    <template v-else>-</template>
                </td>
                <td class="px-4 py-2">{{ order.country ?? '-' }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ order.phone ?? '-' }}
                </td>
                <td class="px-4 py-2">
                    <template v-if="access.can('edit_trip')">
                        <input
                            :id="`order-invoice-${order.id}`"
                            type="text"
                            :value="order.invoice_number ?? ''"
                            :aria-label="
                                t('store.trips.orders.invoiceFor', {
                                    code: order.code,
                                })
                            "
                            maxlength="255"
                            :class="[plainFieldInputClass, 'min-w-28']"
                            @change="saveInvoice(order, $event)"
                        />
                        <template v-if="invoiceOrderId === order.id">
                            <FieldError
                                :message="firstError(invoiceForm.errors)"
                            />
                            <p
                                v-if="savedInvoiceOrderId === order.id"
                                role="status"
                                class="mt-1 text-xs text-green-700 dark:text-green-400"
                            >
                                {{ t('store.trips.orders.invoiceSaved') }}
                            </p>
                        </template>
                    </template>
                    <template v-else>{{ order.invoice_number ?? '' }}</template>
                </td>
                <td class="px-4 py-2 whitespace-pre-line">
                    {{ order.customer_notes ?? '' }}
                </td>
                <td class="px-4 py-2 whitespace-pre-line">
                    {{ order.manager_notes ?? '' }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">{{ order.amount }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ statusLabel(order.delivery_status) }}
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    {{ order.delivery_date ?? '' }}
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="photoUrls(order).length"
                        class="flex flex-wrap gap-1"
                    >
                        <img
                            v-for="url in photoUrls(order)"
                            :key="url"
                            :src="url"
                            :alt="
                                t('store.trips.orders.photoOf', {
                                    code: order.code,
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
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="order.checkpoint"
                            :href="print_cmr(order.checkpoint)"
                            :inertia="false"
                            ><Printer class="h-3.5 w-3.5" />{{
                                t('store.trips.orders.printCmr')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="
                                access.can('edit_trip') &&
                                order.delivery_status !== 'delivered'
                            "
                            :href="markDelivered(order, sameList)"
                            method="put"
                            as="button"
                            preserve-scroll
                            :on-before="
                                () =>
                                    confirmDeliveryStatus(
                                        t('store.trips.orders.order', {
                                            code: order.code,
                                        }),
                                        statusLabel('delivered'),
                                    )
                            "
                            ><CircleCheck class="h-3.5 w-3.5" />{{
                                t('store.trips.orders.markDelivered')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('edit_order') && order.editable"
                            :href="orderEdit(order)"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.trips.orders.edit')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <label
            v-if="canSelect && orders.data.length"
            class="mt-3 flex cursor-pointer items-center gap-2"
        >
            <input
                type="checkbox"
                :checked="allSelected"
                class="cursor-pointer"
                @change="selectAll(($event.target as HTMLInputElement).checked)"
            />{{ t('store.trips.orders.selectAll') }}
        </label>
        <Pagination
            :links="orders.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
