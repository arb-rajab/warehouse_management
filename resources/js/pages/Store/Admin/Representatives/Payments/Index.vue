<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Clock, Download, X } from '@lucide/vue';
import { computed } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    accept,
    exportMethod,
    index as paymentsIndex,
    reject,
} from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePaymentController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { StoreFilterChip } from '@/types/store';
import type { RepresentativePayment } from '@/types/storeRepresentatives';

const props = defineProps<{
    payments: Paginated<RepresentativePayment>;
    filters: {
        per_page: number;
        search: string;
        accepted: boolean;
        rejected: boolean;
        pending: boolean;
    };
}>();
const access = useStoreAccess();
function confirmPayment(receipt: string): boolean {
    return confirm(t('store.representativePayments.confirm', { receipt }));
}
const statuses = ['pending', 'accepted', 'rejected'] as const;
/** Dialog filters only: one chip per ticked status box. */
const appliedFilters = computed<StoreFilterChip[]>(() =>
    statuses
        .filter((status) => props.filters[status])
        .map((status) => ({
            key: status,
            label: t('store.representativePayments.status'),
            value: t(`store.representativePayments.${status}`),
            keys: [status],
        })),
);
/** Change the page size, keeping the applied search and statuses. */
function changePerPage(perPage: number): void {
    router.get(
        paymentsIndex().url,
        {
            search: props.filters.search,
            accepted: props.filters.accepted,
            rejected: props.filters.rejected,
            pending: props.filters.pending,
            per_page: perPage,
        },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'representatives.payments',
    columns: [
        { key: 'id', label: 'store.representativePayments.id' },
        {
            key: 'customer',
            label: 'store.representativePayments.customer',
            required: true,
        },
        { key: 'accountId', label: 'store.representativePayments.accountId' },
        {
            key: 'representative',
            label: 'store.representativePayments.representative',
        },
        { key: 'receipt', label: 'store.representativePayments.receipt' },
        { key: 'invoice', label: 'store.representativePayments.invoice' },
        { key: 'amount', label: 'store.representativePayments.amount' },
        { key: 'accountant', label: 'store.representativePayments.accountant' },
        { key: 'notes', label: 'store.representativePayments.notes' },
        { key: 'status', label: 'store.representativePayments.status' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.representativePayments.actions'),
]);
</script>

<template>
    <Head :title="t('store.representativePayments.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.representativePayments.title')" />
        <StoreListFilters :action="paymentsIndex()" :chips="appliedFilters">
            <template #default="{ errors }">
                <input
                    type="hidden"
                    name="per_page"
                    :value="filters.per_page"
                />
                <FormField
                    id="search"
                    :value="filters.search"
                    :error="errors.search"
                    :label="t('store.representativePayments.search')"
                    maxlength="255"
                />
            </template>
            <template #filters="{ errors }">
                <fieldset>
                    <legend :class="fieldLabelClass">
                        {{ t('store.representativePayments.status') }}
                    </legend>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="status in statuses"
                            :key="status"
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                :name="status"
                                value="1"
                                :checked="filters[status]"
                                class="cursor-pointer"
                            />
                            {{ t(`store.representativePayments.${status}`) }}
                        </label>
                    </div>
                    <FieldError
                        :message="
                            errors.pending ?? errors.accepted ?? errors.rejected
                        "
                    />
                </fieldset>
            </template>
        </StoreListFilters>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="payments.data"
            :empty-message="t('store.representativePayments.empty')"
        >
            <template #row="{ row: payment }">
                <td v-if="shown.id" class="px-4 py-2">{{ payment.id }}</td>
                <td class="px-4 py-2">
                    <TableObjectLink
                        v-if="payment.customer_id !== null"
                        :allowed="access.can('edit_customer')"
                        :href="customerEdit(payment.customer_id)"
                        >{{ payment.customer_name }}</TableObjectLink
                    ><template v-else>{{ payment.customer_name }}</template>
                </td>
                <td v-if="shown.accountId" class="px-4 py-2">
                    {{ payment.account_id }}
                </td>
                <td v-if="shown.representative" class="px-4 py-2">
                    <TableObjectLink
                        v-if="payment.representative"
                        :allowed="access.can('edit_customer')"
                        :href="customerEdit(payment.representative)"
                        >{{ payment.representative.name }}</TableObjectLink
                    >
                </td>
                <td v-if="shown.receipt" class="px-4 py-2">
                    {{ payment.receipt_code }}
                </td>
                <td v-if="shown.invoice" class="px-4 py-2">
                    {{ payment.invoice_code }}
                </td>
                <td v-if="shown.amount" class="px-4 py-2">
                    {{ payment.amount }}
                </td>
                <td v-if="shown.accountant" class="px-4 py-2">
                    {{ payment.accountant?.name }}
                </td>
                <td
                    v-if="shown.notes"
                    class="max-w-xs truncate px-4 py-2"
                    :title="payment.notes"
                >
                    {{ payment.notes }}
                </td>
                <td v-if="shown.status" class="px-4 py-2">
                    <span class="inline-flex items-center gap-1"
                        ><Clock
                            v-if="payment.status === 'pending'"
                            class="h-3.5 w-3.5"
                        /><Check
                            v-else-if="payment.status === 'accepted'"
                            class="h-3.5 w-3.5"
                        /><X v-else class="h-3.5 w-3.5" />{{
                            t(`store.representativePayments.${payment.status}`)
                        }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div class="flex flex-nowrap items-center gap-2">
                        <template
                            v-if="
                                payment.status === 'pending' &&
                                access.can('approve_representative_payment')
                            "
                        >
                            <TableActionLink
                                :href="accept(payment, { mergeQuery: {} })"
                                method="post"
                                as="button"
                                :on-before="
                                    () => confirmPayment(payment.receipt_code)
                                "
                                ><Check class="h-3.5 w-3.5" />{{
                                    t('store.representativePayments.accept')
                                }}</TableActionLink
                            >
                            <TableActionLink
                                :href="reject(payment, { mergeQuery: {} })"
                                method="post"
                                as="button"
                                variant="danger"
                                :on-before="
                                    () => confirmPayment(payment.receipt_code)
                                "
                                ><X class="h-3.5 w-3.5" />{{
                                    t('store.representativePayments.reject')
                                }}</TableActionLink
                            >
                        </template>
                        <a
                            v-if="access.can('export_representative_payment')"
                            :href="exportMethod.url(payment)"
                            class="inline-flex items-center gap-1 text-sm underline"
                            ><Download class="h-3.5 w-3.5" />{{
                                t('store.representativePayments.export')
                            }}</a
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="payments.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
