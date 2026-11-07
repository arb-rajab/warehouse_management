<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { invoice_download } from '@/actions/App/Http/Controllers/InvoiceController';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    update_delivery_status,
    update_payment_status,
    update_tracking_code,
    update_manager_notes,
    edit_item_price,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import { edit } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import { edit as productEdit } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import AddResourceLink from '@/components/AddResourceLink.vue';
import AddressMapLink from '@/components/AddressMapLink.vue';
import DataTable from '@/components/DataTable.vue';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { joinAddressParts } from '@/lib/address';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { provideRequiredFields } from '@/lib/requiredFields';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreOrder } from '@/types/store';
const props = defineProps<{
    order: StoreOrder;
    additionalInfo: string | null;
    trackingCode: string | null;
    shippingAddress: Record<string, string | null>;
    couponDiscount: number;
}>();
const access = useStoreAccess();
const editable = computed(
    () =>
        props.order.editable &&
        props.order.payment_status === 'unpaid' &&
        !['delivered', 'cancelled'].includes(props.order.delivery_status),
);
const actions = computed(() => [
    {
        id: 'delivery',
        label: 'deliveryStatus',
        permission: 'update_order_delivery_status',
        action: update_delivery_status(),
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
        current: props.order.delivery_status,
        visible: !['delivered', 'cancelled'].includes(
            props.order.delivery_status,
        ),
    },
    {
        id: 'payment',
        label: 'paymentStatus',
        permission: 'update_order_payment_status',
        action: update_payment_status(),
        statuses: ['unpaid', 'paid'],
        current: props.order.payment_status,
        visible: props.order.delivery_status !== 'cancelled',
    },
]);
const hasRequiredFields = provideRequiredFields();
</script>
<template>
    <Head :title="t('store.orders.show', { code: order.code })" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.orders.show', { code: order.code })"
            ><AddResourceLink
                v-if="editable && !order.by_rep && access.can('edit_order')"
                :href="edit(order)"
                :label="t('store.orders.edit')"
        /></PageHeader>
        <div class="mb-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <DetailList :title="t('store.orders.groups.order')">
                <DetailItem
                    :label="t('store.orders.tracking')"
                    :value="trackingCode"
                />
                <DetailItem
                    :label="t('store.orders.deliveryDate')"
                    :value="order.delivery_date"
                />
                <DetailItem
                    :label="t('store.orders.indicator')"
                    :value="`${order.indicator}%`"
                />
            </DetailList>
            <DetailList :title="t('store.orders.groups.people')">
                <DetailItem :label="t('store.orders.customer')"
                    ><TableObjectLink
                        v-if="order.customer"
                        :allowed="access.can('edit_customer')"
                        :href="customerEdit(order.customer)"
                        >{{ order.customer.name }}</TableObjectLink
                    ></DetailItem
                >
                <DetailItem :label="t('store.orders.representative')"
                    ><TableObjectLink
                        v-if="order.representative"
                        :allowed="access.can('edit_customer')"
                        :href="customerEdit(order.representative)"
                        >{{ order.representative.name }}</TableObjectLink
                    ></DetailItem
                >
            </DetailList>
            <DetailList
                :title="t('store.orders.groups.money')"
                :columns="2"
                keep-columns
                class="md:col-span-2 lg:col-span-1"
            >
                <DetailItem
                    amount
                    :label="t('store.orders.total')"
                    :value="order.grand_total.toFixed(2)"
                />
                <DetailItem
                    amount
                    :label="t('store.orders.couponDiscount')"
                    :value="couponDiscount.toFixed(2)"
                />
                <DetailItem
                    amount
                    :label="t('store.orders.discount')"
                    :value="`${order.discount_percent}%`"
                />
                <DetailItem
                    amount
                    :label="t('store.orders.profits')"
                    :value="order.profits.toFixed(2)"
                />
            </DetailList>
            <DetailList
                :title="t('store.orders.groups.notes')"
                :columns="2"
                class="md:col-span-2 lg:col-span-3"
            >
                <DetailItem
                    :label="t('store.orders.managerNotes')"
                    :value="order.manager_notes"
                />
                <DetailItem
                    preserve
                    :label="t('store.orders.notes')"
                    :value="additionalInfo"
                />
                <DetailItem full :label="t('store.orders.address')"
                    ><AddressMapLink
                        :address="
                            joinAddressParts(Object.values(shippingAddress))
                        "
                        placeholder="-"
                /></DetailItem>
            </DetailList>
        </div>
        <a
            v-if="access.can('view_order_details')"
            :href="invoice_download.url(order.id)"
            class="mb-4 inline-block text-blue-700 dark:text-blue-400"
            >{{ t('store.orders.invoice') }}</a
        >
        <div class="mb-5 grid gap-4 md:grid-cols-2">
            <template v-for="action in actions" :key="action.id">
                <Form
                    v-if="access.can(action.permission) && action.visible"
                    :action="action.action"
                    #default="{ processing, errors }"
                    class="space-y-2"
                >
                    <RequiredLegend v-if="hasRequiredFields" />
                    <input
                        name="order_id"
                        type="hidden"
                        :value="order.id"
                    /><label :for="action.id" :class="fieldLabelClass"
                        >{{ t(`store.orders.${action.label}`) }}<RequiredMark
                    /></label>
                    <select
                        :id="action.id"
                        name="status"
                        :value="action.current"
                        :class="plainFieldInputClass"
                        required
                    >
                        <option
                            v-for="status in action.statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ t(`store.orders.status.${status}`) }}
                        </option>
                    </select>
                    <ActionErrorBanner
                        :message="firstError(errors)"
                    /><SubmitButton
                        :label="t('store.orders.save')"
                        :processing-label="t('store.orders.saving')"
                        :processing="processing"
                    />
                </Form>
                <p v-else>
                    {{ t(`store.orders.${action.label}`) }}:
                    {{ t(`store.orders.status.${action.current}`) }}
                </p>
            </template>
            <Form
                v-if="access.can('update_order_tracking')"
                :action="update_tracking_code()"
                #default="{ processing, errors }"
                class="space-y-2"
                ><input
                    name="order_id"
                    type="hidden"
                    :value="order.id" /><FormField
                    id="tracking_code"
                    :label="t('store.orders.tracking')"
                    :value="trackingCode ?? ''"
                    maxlength="255"
                    :error="errors.tracking_code" /><SubmitButton
                    :label="t('store.orders.save')"
                    :processing-label="t('store.orders.saving')"
                    :processing="processing"
            /></Form>
            <Form
                v-if="access.can('update_order_notes')"
                :action="update_manager_notes()"
                #default="{ processing, errors }"
                class="space-y-2"
                ><input
                    name="order_id"
                    type="hidden"
                    :value="order.id" /><FormField
                    id="notes"
                    :label="t('store.orders.managerNotes')"
                    :value="order.manager_notes ?? ''"
                    maxlength="2000"
                    :error="errors.notes" /><SubmitButton
                    :label="t('store.orders.save')"
                    :processing-label="t('store.orders.saving')"
                    :processing="processing"
            /></Form>
        </div>
        <DataTable
            :columns="[
                t('store.orders.product'),
                t('store.orders.quantity'),
                t('store.orders.variant'),
                t('store.orders.total'),
                t('store.orders.tax'),
                t('store.orders.shipping'),
                t('store.orders.unitPrice'),
            ]"
            :rows="order.details ?? []"
            :empty-message="t('store.orders.noLines')"
        >
            <template #row="{ row }"
                ><td class="px-3 py-3">
                    <TableObjectLink
                        :allowed="access.can('product_edit')"
                        :href="productEdit(row.product_id)"
                        >{{ row.name }}</TableObjectLink
                    >
                    {{ row.serial }}
                    <p>{{ row.info }}</p>
                </td>
                <td class="px-3 py-3">{{ row.quantity }}</td>
                <td class="px-3 py-3">{{ row.variation }} {{ row.unit }}</td>
                <td class="px-3 py-3">{{ row.price.toFixed(2) }}</td>
                <td class="px-3 py-3">{{ row.tax.toFixed(2) }}</td>
                <td class="px-3 py-3">{{ row.shipping_cost.toFixed(2) }}</td>
                <td class="px-3 py-3">
                    <Form
                        v-if="editable && access.can('edit_order_price')"
                        :action="edit_item_price()"
                        #default="{ processing, errors }"
                        class="space-y-2"
                        ><RequiredLegend v-if="hasRequiredFields" /><input
                            name="id"
                            type="hidden"
                            :value="row.id" /><FormField
                            :id="`price-${row.id}`"
                            name="admin_price"
                            :label="t('store.orders.unitPrice')"
                            type="number"
                            :value="row.unit_price"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            required
                            :error="errors.admin_price" /><ActionErrorBanner
                            :message="firstError(errors)" /><SubmitButton
                            :label="t('store.orders.save')"
                            :processing-label="t('store.orders.saving')"
                            :processing="processing" /></Form
                    ><span v-else>{{ row.unit_price.toFixed(2) }}</span>
                </td></template
            >
        </DataTable>
    </StoreAdminLayout>
</template>
