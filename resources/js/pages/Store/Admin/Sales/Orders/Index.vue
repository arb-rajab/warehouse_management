<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
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
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { usePageError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreOrder } from '@/types/store';
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
        <form
            method="get"
            :action="listAction.url()"
            class="mb-5 grid gap-3 sm:grid-cols-3"
        >
            <FormField
                id="search"
                :label="t('store.orders.search')"
                :value="filters.search"
                maxlength="100"
            />
            <div
                v-for="filter in [
                    {
                        id: 'payment_status',
                        label: 'paymentStatus',
                        statuses: ['paid', 'unpaid'],
                    },
                    {
                        id: 'delivery_status',
                        label: 'deliveryStatus',
                        statuses: [
                            'pending',
                            'confirmed',
                            'picked_up',
                            'on_the_way',
                            'on_delivery',
                            'ready_for_delivery',
                            'delivered',
                            'cancelled',
                        ],
                    },
                ]"
                :key="filter.id"
            >
                <label :for="filter.id" :class="fieldLabelClass">{{
                    t(`store.orders.${filter.label}`)
                }}</label>
                <select
                    :id="filter.id"
                    :name="filter.id"
                    :value="
                        filter.id === 'payment_status'
                            ? (filters.payment_status ?? '')
                            : (filters.delivery_status ?? '')
                    "
                    :class="plainFieldInputClass"
                >
                    <option value="">
                        {{ t('store.orders.allStatuses') }}
                    </option>
                    <option
                        v-for="status in filter.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ t(`store.orders.status.${status}`) }}
                    </option>
                </select>
            </div>
            <FormField
                id="from"
                :label="t('store.orders.from')"
                type="date"
                :value="filters.from"
                class="dark:[color-scheme:dark]"
            />
            <FormField
                id="to"
                :label="t('store.orders.to')"
                type="date"
                :value="filters.to"
                :min="filters.from"
                class="dark:[color-scheme:dark]"
            />
            <input name="per_page" type="hidden" :value="filters.per_page" />
            <SubmitButton
                :label="t('common.filters.apply')"
                :processing-label="t('common.filters.apply')"
                :processing="false"
            />
        </form>
        <Form
            :action="bulk_order_delete({ mergeQuery: {} })"
            :options="{ preserveScroll: true }"
            #default="{ processing }"
        >
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
                            type="checkbox"
                            name="id[]"
                            :value="row.id"
                            :aria-label="
                                t('store.orders.selectOrder', {
                                    code: row.code,
                                })
                            "
                        />
                    </td>
                    <td class="px-3 py-3">{{ row.code }}</td>
                    <td class="px-3 py-3">{{ row.customer?.name }}</td>
                    <td class="px-3 py-3">{{ row.grand_total.toFixed(2) }}</td>
                    <td class="px-3 py-3">
                        {{ t(`store.orders.status.${row.delivery_status}`) }}
                    </td>
                    <td class="px-3 py-3">
                        {{ t(`store.orders.status.${row.payment_status}`) }}
                    </td>
                    <td class="space-x-2 px-3 py-3">
                        <TableActionLink
                            v-if="access.can('view_order_details')"
                            :href="
                                show['/temp/admin/orders/{order}/show'](row.id)
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
                        if (!confirmDelete(t('store.orders.selectedOrders')))
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
        </Form>
        <Pagination
            :links="orders.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
