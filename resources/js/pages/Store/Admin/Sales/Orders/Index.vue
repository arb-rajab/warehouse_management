<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    all_orders,
    show,
    destroy,
    bulk_order_delete,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    create,
    shippingCreate,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { usePageError } from '@/lib/pageErrors';
import { provideRequiredFields } from '@/lib/requiredFields';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreFilterChip, StoreOrder } from '@/types/store';
const props = defineProps<{
    orders: Paginated<StoreOrder>;
    filters: {
        search?: string;
        payment_status?: string;
        delivery_status?: string;
        from?: string;
        to?: string;
        per_page: number;
    };
    listRoute: string;
}>();
const access = useStoreAccess();
const selected = ref<number[]>([]);
const error = usePageError('action');
const listAction = computed(
    () =>
        all_orders[
            props.listRoute === 'inhouse_orders.index'
                ? '/temp/admin/inhouse-orders'
                : props.listRoute === 'seller_orders.index'
                  ? '/temp/admin/seller_orders'
                  : props.listRoute === 'pick_up_point.index'
                    ? '/temp/admin/orders_by_pickup_point'
                    : '/temp/admin/all_orders'
        ],
);
function changePerPage(perPage: number): void {
    router.get(
        listAction.value.url({
            query: { ...props.filters, per_page: perPage },
        }),
    );
}
const paymentStatuses = ['paid', 'unpaid'];
const deliveryStatuses = [
    'pending',
    'confirmed',
    'picked_up',
    'on_the_way',
    'on_delivery',
    'ready_for_delivery',
    'delivered',
    'cancelled',
];
/** Dialog filters only, one chip each: payment status and each date bound. */
const appliedFilters = computed<StoreFilterChip[]>(() => {
    const { payment_status: payment, from, to } = props.filters;
    const chips: StoreFilterChip[] = [];

    if (payment) {
        chips.push({
            key: 'payment_status',
            label: t('store.orders.paymentStatus'),
            value: t(`store.orders.status.${payment}`),
            keys: ['payment_status'],
        });
    }

    if (from) {
        chips.push({
            key: 'from',
            label: t('store.orders.from'),
            value: from,
            keys: ['from'],
        });
    }

    if (to) {
        chips.push({
            key: 'to',
            label: t('store.orders.to'),
            value: to,
            keys: ['to'],
        });
    }

    return chips;
});
const hasRequiredFields = provideRequiredFields();
</script>
<template>
    <Head :title="t('store.orders.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.orders.title')"
            ><div v-if="access.can('add_order')" class="flex gap-2">
                <AddResourceLink
                    :href="create()"
                    :label="t('store.orders.createRepresentative')"
                /><AddResourceLink
                    :href="shippingCreate()"
                    :label="t('store.orders.createShipping')"
                /></div
        ></PageHeader>
        <ActionErrorBanner :message="error" />
        <StoreListFilters :action="listAction()" :chips="appliedFilters">
            <template #default="{ errors }">
                <input
                    name="per_page"
                    type="hidden"
                    :value="filters.per_page"
                />
                <FormField
                    id="search"
                    :label="t('store.orders.search')"
                    :value="filters.search"
                    :error="errors.search"
                    maxlength="100"
                />
                <div>
                    <label for="delivery_status" :class="fieldLabelClass">{{
                        t('store.orders.deliveryStatus')
                    }}</label>
                    <select
                        id="delivery_status"
                        name="delivery_status"
                        :class="plainFieldInputClass"
                    >
                        <option value="">
                            {{ t('store.orders.allStatuses') }}
                        </option>
                        <option
                            v-for="status in deliveryStatuses"
                            :key="status"
                            :value="status"
                            :selected="filters.delivery_status === status"
                        >
                            {{ t(`store.orders.status.${status}`) }}
                        </option>
                    </select>
                    <FieldError :message="errors.delivery_status" />
                </div>
            </template>
            <template #filters="{ errors }">
                <div>
                    <label for="payment_status" :class="fieldLabelClass">{{
                        t('store.orders.paymentStatus')
                    }}</label>
                    <select
                        id="payment_status"
                        name="payment_status"
                        :class="plainFieldInputClass"
                    >
                        <option value="">
                            {{ t('store.orders.allStatuses') }}
                        </option>
                        <option
                            v-for="status in paymentStatuses"
                            :key="status"
                            :value="status"
                            :selected="filters.payment_status === status"
                        >
                            {{ t(`store.orders.status.${status}`) }}
                        </option>
                    </select>
                    <FieldError :message="errors.payment_status" />
                </div>
                <FormField
                    id="from"
                    :label="t('store.orders.from')"
                    type="date"
                    :value="filters.from"
                    :error="errors.from"
                    class="dark:[color-scheme:dark]"
                />
                <FormField
                    id="to"
                    :label="t('store.orders.to')"
                    type="date"
                    :value="filters.to"
                    :min="filters.from"
                    :error="errors.to"
                    class="dark:[color-scheme:dark]"
                />
            </template>
        </StoreListFilters>
        <Form
            :action="bulk_order_delete({ mergeQuery: {} })"
            :options="{ preserveScroll: true }"
            #default="{ processing }"
            @success="selected = []"
        >
            <RequiredLegend
                v-if="hasRequiredFields && access.can('delete_order')"
            />
            <fieldset>
                <legend
                    v-if="access.can('delete_order')"
                    :class="fieldLabelClass"
                >
                    {{ t('store.orders.selectedOrders') }}<RequiredMark />
                </legend>
                <DataTable
                    :columns="[
                        t('store.orders.select'),
                        t('store.orders.code'),
                        t('store.orders.customer'),
                        t('store.orders.total'),
                        t('store.orders.deliveryStatus'),
                        t('store.orders.paymentStatus'),
                        t('store.orders.actions'),
                    ]"
                    :rows="orders.data"
                    :empty-message="t('store.orders.empty')"
                >
                    <template #row="{ row }">
                        <td class="px-3 py-3">
                            <input
                                v-if="access.can('delete_order')"
                                v-model="selected"
                                type="checkbox"
                                name="id[]"
                                :required="
                                    selected.length === 0 &&
                                    row.id === orders.data[0]?.id
                                "
                                :value="row.id"
                                :aria-label="
                                    t('store.orders.selectOrder', {
                                        code: row.code,
                                    })
                                "
                            />
                        </td>
                        <td class="px-3 py-3">{{ row.code }}</td>
                        <td class="px-3 py-3">
                            <TableObjectLink
                                v-if="row.customer"
                                :allowed="access.can('edit_customer')"
                                :href="customerEdit(row.customer)"
                                >{{ row.customer.name }}</TableObjectLink
                            >
                        </td>
                        <td class="px-3 py-3">
                            {{ row.grand_total.toFixed(2) }}
                        </td>
                        <td class="px-3 py-3">
                            {{
                                t(`store.orders.status.${row.delivery_status}`)
                            }}
                        </td>
                        <td class="px-3 py-3">
                            {{ t(`store.orders.status.${row.payment_status}`) }}
                        </td>
                        <td class="space-x-2 px-3 py-3">
                            <TableActionLink
                                v-if="access.can('view_order_details')"
                                :href="
                                    show['/temp/admin/orders/{order}/show'](
                                        row.id,
                                    )
                                "
                                :label="t('store.orders.view')"
                                >{{ t('store.orders.view') }}</TableActionLink
                            ><TableActionLink
                                v-if="access.can('delete_order')"
                                :href="destroy(row, { mergeQuery: {} })"
                                as="button"
                                variant="danger"
                                :on-before="() => confirmDelete(row.code)"
                                >{{ t('store.orders.delete') }}</TableActionLink
                            >
                        </td>
                    </template>
                </DataTable>
                <div
                    v-if="access.can('delete_order')"
                    class="mt-3"
                    @click.capture="
                        (event) => {
                            if (
                                !confirmDelete(t('store.orders.selectedOrders'))
                            )
                                event.preventDefault();
                        }
                    "
                >
                    <SubmitButton
                        :label="t('store.orders.deleteSelected')"
                        :processing-label="t('store.orders.saving')"
                        :processing="processing"
                    />
                </div>
            </fieldset>
        </Form>
        <Pagination
            :links="orders.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
