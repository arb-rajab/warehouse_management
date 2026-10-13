<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { invoice_download } from '@/actions/App/Http/Controllers/InvoiceController';
import {
    update_delivery_status,
    update_payment_status,
    update_tracking_code,
    update_manager_notes,
    edit_item_price,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import { edit } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
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
        <dl class="mb-5 grid grid-cols-2 gap-2 text-sm">
            <dt>{{ t('store.orders.tracking') }}</dt>
            <dd>{{ trackingCode }}</dd>
            <dt>{{ t('store.orders.managerNotes') }}</dt>
            <dd>{{ order.manager_notes }}</dd>
            <dt>{{ t('store.orders.customer') }}</dt>
            <dd>{{ order.customer?.name }}</dd>
            <dt>{{ t('store.orders.representative') }}</dt>
            <dd>{{ order.representative?.name }}</dd>
            <dt>{{ t('store.orders.deliveryDate') }}</dt>
            <dd>{{ order.delivery_date }}</dd>
            <dt>{{ t('store.orders.notes') }}</dt>
            <dd class="whitespace-pre-wrap">{{ additionalInfo }}</dd>
            <dt>{{ t('store.orders.address') }}</dt>
            <dd>
                {{ Object.values(shippingAddress).filter(Boolean).join(', ') }}
            </dd>
            <dt>{{ t('store.orders.total') }}</dt>
            <dd>{{ order.grand_total.toFixed(2) }}</dd>
            <dt>{{ t('store.orders.couponDiscount') }}</dt>
            <dd>{{ couponDiscount.toFixed(2) }}</dd>
            <dt>{{ t('store.orders.profits') }}</dt>
            <dd>{{ order.profits.toFixed(2) }}</dd>
            <dt>{{ t('store.orders.discount') }}</dt>
            <dd>{{ order.discount_percent }}%</dd>
            <dt>{{ t('store.orders.indicator') }}</dt>
            <dd>{{ order.indicator }}%</dd>
        </dl>
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
                    <input
                        name="order_id"
                        type="hidden"
                        :value="order.id"
                    /><label :for="action.id" :class="fieldLabelClass">{{
                        t(`store.orders.${action.label}`)
                    }}</label>
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
                    {{ row.name }} {{ row.serial }}
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
                        ><input
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
