<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    index as paymentsIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreSellerPayment } from '@/types/store';
const props = defineProps<{
    payments: Paginated<StoreSellerPayment>;
    seller: StoreSellerPayment['seller'] | null;
    filters: { per_page: number };
}>();
const access = useStoreAccess();
function apply(perPage: number): void {
    const route = props.seller ? show(props.seller) : paymentsIndex();
    router.get(
        route.url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <StoreAdminLayout>
        <Head :title="t('store.sellers.paymentsTitle')" />
        <PageHeader :title="t('store.sellers.paymentsTitle')"
            ><TableActionLink
                v-if="seller && access.can('seller_payment_history')"
                :href="paymentsIndex()"
                >{{ t('store.sellers.allPayments') }}</TableActionLink
            ></PageHeader
        >
        <p v-if="seller" class="mb-4">
            {{ seller.name }} ({{ seller.shop_name }})
        </p>
        <DataTable
            :rows="payments.data"
            :columns="[
                t('store.sellers.id'),
                t('store.sellers.date'),
                t('store.sellers.seller'),
                t('store.sellers.amount'),
                t('store.sellers.paymentDetails'),
            ]"
            :empty-message="t('store.sellers.noPayments')"
        >
            <template #row="{ row }"
                ><td class="px-4 py-3">{{ row.id }}</td>
                <td class="px-4 py-3">{{ row.date }}</td>
                <td class="px-4 py-3">
                    <TableLink
                        v-if="access.can('seller_payment_history')"
                        :href="show(row.seller)"
                        >{{ row.seller.name }} ({{
                            row.seller.shop_name
                        }})</TableLink
                    ><span v-else
                        >{{ row.seller.name }} ({{
                            row.seller.shop_name
                        }})</span
                    >
                </td>
                <td class="px-4 py-3">{{ row.amount }}</td>
                <td class="px-4 py-3">
                    {{ row.payment_method
                    }}<span v-if="row.txn_code">
                        ({{ t('store.sellers.txnCode') }}:
                        {{ row.txn_code }})</span
                    >
                </td></template
            >
        </DataTable>
        <Pagination
            :links="payments.meta.links"
            :per-page="filters.per_page"
            @per-page-change="apply"
        />
    </StoreAdminLayout>
</template>
