<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Banknote, Check, Clock, Eye } from '@lucide/vue';
import { ref } from 'vue';
import { show } from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import { index as withdrawalsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SellerWithdrawRequestController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSellerPaymentDialog from '@/components/StoreSellerPaymentDialog.vue';
import StoreSellerWithdrawMessageDialog from '@/components/StoreSellerWithdrawMessageDialog.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
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
</script>
<template>
    <StoreAdminLayout>
        <Head :title="t('store.sellers.withdrawalsTitle')" />
        <PageHeader :title="t('store.sellers.withdrawalsTitle')" />
        <DataTable
            :rows="withdrawals.data"
            :columns="
                [
                    'id',
                    'date',
                    'seller',
                    'due',
                    'requestedAmount',
                    'message',
                    'status',
                    'options',
                ].map((key) => t(`store.sellers.${key}`))
            "
            :empty-message="t('store.sellers.noWithdrawals')"
        >
            <template #row="{ row }"
                ><td class="px-4 py-3">{{ row.id }}</td>
                <td class="px-4 py-3">{{ row.date }}</td>
                <td class="px-4 py-3">
                    {{ row.seller.name }} ({{ row.seller.shop_name }})
                </td>
                <td class="px-4 py-3">{{ row.due }}</td>
                <td class="px-4 py-3">{{ row.amount }}</td>
                <td class="px-4 py-3 whitespace-pre-wrap">{{ row.message }}</td>
                <td class="px-4 py-3">
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
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="access.can('pay_to_seller')"
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 text-blue-700 underline dark:text-blue-400"
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
                            class="inline-flex cursor-pointer items-center gap-1 text-blue-700 underline dark:text-blue-400"
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
