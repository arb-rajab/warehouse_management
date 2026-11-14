<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Banknote, Check, Clock, Eye } from '@lucide/vue';
import { ref, computed } from 'vue';
import { edit as sellerEdit } from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import { show } from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import { index as withdrawalsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SellerWithdrawRequestController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreSellerPaymentDialog from '@/components/StoreSellerPaymentDialog.vue';
import StoreSellerWithdrawMessageDialog from '@/components/StoreSellerWithdrawMessageDialog.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { StoreSellerWithdrawal } from '@/types/store';
defineProps<{
    withdrawals: Paginated<StoreSellerWithdrawal>;
    filters: { per_page: number };
}>();
const access = useStoreAccess();
const selected = ref<StoreSellerWithdrawal | null>(null);
const paymentOpen = ref(false);
const messageOpen = ref(false);
function apply(perPage: number): void {
    router.get(
        withdrawalsIndex['/temp/admin/withdraw_requests_all'].url(),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'sellers.withdrawals',
    columns: [
        { key: 'id', label: 'store.sellers.id' },
        { key: 'date', label: 'store.sellers.date' },
        { key: 'seller', label: 'store.sellers.seller', required: true },
        { key: 'due', label: 'store.sellers.due' },
        { key: 'requestedAmount', label: 'store.sellers.requestedAmount' },
        { key: 'message', label: 'store.sellers.message' },
        { key: 'status', label: 'store.sellers.status' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.sellers.options'),
]);
</script>
<template>
    <StoreAdminLayout>
        <Head :title="t('store.sellers.withdrawalsTitle')" />
        <PageHeader :title="t('store.sellers.withdrawalsTitle')" />
        <StoreColumnPicker :state="columns" />
        <DataTable
            :rows="withdrawals.data"
            :columns="tableColumns"
            :empty-message="t('store.sellers.noWithdrawals')"
        >
            <template #row="{ row }"
                ><td v-if="shown.id" class="px-4 py-3">{{ row.id }}</td>
                <td v-if="shown.date" class="px-4 py-3">{{ row.date }}</td>
                <td class="px-4 py-3">
                    <TableObjectLink
                        v-if="row.seller.shop_id !== null"
                        :allowed="access.can('edit_seller')"
                        :href="sellerEdit(row.seller.shop_id)"
                        >{{ row.seller.name }}</TableObjectLink
                    ><template v-else>{{ row.seller.name }}</template> ({{
                        row.seller.shop_name
                    }})
                </td>
                <td v-if="shown.due" class="px-4 py-3">{{ row.due }}</td>
                <td v-if="shown.requestedAmount" class="px-4 py-3">
                    {{ row.amount }}
                </td>
                <td
                    v-if="shown.message"
                    class="max-w-xs truncate px-4 py-3"
                    :title="row.message ?? undefined"
                >
                    {{ row.message }}
                </td>
                <td v-if="shown.status" class="px-4 py-3">
                    <span class="inline-flex items-center gap-1"
                        ><component
                            :is="row.paid ? Check : Clock"
                            class="h-4 w-4"
                        />{{
                            t(
                                row.paid
                                    ? 'store.sellers.paid'
                                    : 'store.sellers.pending',
                            )
                        }}</span
                    >
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-nowrap gap-2">
                        <button
                            v-if="access.can('pay_to_seller')"
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 text-link underline"
                            @click="
                                selected = row;
                                paymentOpen = true;
                            "
                        >
                            <Banknote class="h-4 w-4" />{{
                                t('store.sellers.payNow')
                            }}
                        </button>
                        <button
                            v-if="access.can('view_seller_payout_requests')"
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 text-link underline"
                            @click="
                                selected = row;
                                messageOpen = true;
                            "
                        >
                            <Eye class="h-4 w-4" />{{
                                t('store.sellers.viewMessage')
                            }}
                        </button>
                        <TableActionLink
                            v-if="access.can('seller_payment_history')"
                            :href="show(row.seller)"
                            >{{
                                t('store.sellers.paymentHistory')
                            }}</TableActionLink
                        >
                    </div>
                </td></template
            >
        </DataTable>
        <Pagination
            :links="withdrawals.meta.links"
            :per-page="filters.per_page"
            @per-page-change="apply"
        />
        <StoreSellerPaymentDialog
            v-model:open="paymentOpen"
            :shop-id="null"
            :withdrawal="selected"
        />
        <StoreSellerWithdrawMessageDialog
            v-model:open="messageOpen"
            :withdrawal="selected"
        />
    </StoreAdminLayout>
</template>
