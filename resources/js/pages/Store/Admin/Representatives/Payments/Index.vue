<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Clock, Download, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    accept,
    exportMethod,
    index as paymentsIndex,
    reject,
} from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePaymentController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
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
const search = ref(props.filters.search);
const accepted = ref(props.filters.accepted);
const rejected = ref(props.filters.rejected);
const pending = ref(props.filters.pending);
function applyFilters(perPage = props.filters.per_page): void {
    router.get(
        paymentsIndex().url,
        {
            search: search.value,
            accepted: accepted.value,
            rejected: rejected.value,
            pending: pending.value,
            per_page: perPage,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.representativePayments.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.representativePayments.title')" />
        <form
            class="mb-4 flex flex-wrap items-end gap-3"
            @submit.prevent="applyFilters()"
        >
            <FormField
                id="search"
                :value="search"
                @input="search = ($event.target as HTMLInputElement).value"
                :label="t('store.representativePayments.search')"
                maxlength="255"
            />
            <label
                v-for="status in ['pending', 'accepted', 'rejected']"
                :key="status"
                class="flex items-center gap-2"
            >
                <input
                    v-if="status === 'accepted'"
                    v-model="accepted"
                    type="checkbox"
                    class="cursor-pointer"
                />
                <input
                    v-else-if="status === 'rejected'"
                    v-model="rejected"
                    type="checkbox"
                    class="cursor-pointer"
                />
                <input
                    v-else
                    v-model="pending"
                    type="checkbox"
                    class="cursor-pointer"
                />
                {{ t(`store.representativePayments.${status}`) }}
            </label>
            <SubmitButton
                :label="t('common.filters.apply')"
                :processing="false"
                :processing-label="t('common.filters.apply')"
            />
        </form>
        <DataTable
            :columns="[
                t('store.representativePayments.id'),
                t('store.representativePayments.customer'),
                t('store.representativePayments.accountId'),
                t('store.representativePayments.representative'),
                t('store.representativePayments.receipt'),
                t('store.representativePayments.invoice'),
                t('store.representativePayments.amount'),
                t('store.representativePayments.accountant'),
                t('store.representativePayments.notes'),
                t('store.representativePayments.status'),
                t('store.representativePayments.actions'),
            ]"
            :rows="payments.data"
            :empty-message="t('store.representativePayments.empty')"
        >
            <template #row="{ row: payment }">
                <td class="px-4 py-2">{{ payment.id }}</td>
                <td class="px-4 py-2">{{ payment.customer_name }}</td>
                <td class="px-4 py-2">{{ payment.account_id }}</td>
                <td class="px-4 py-2">{{ payment.representative?.name }}</td>
                <td class="px-4 py-2">{{ payment.receipt_code }}</td>
                <td class="px-4 py-2">{{ payment.invoice_code }}</td>
                <td class="px-4 py-2">{{ payment.amount }}</td>
                <td class="px-4 py-2">{{ payment.accountant?.name }}</td>
                <td class="px-4 py-2">{{ payment.notes }}</td>
                <td class="px-4 py-2">
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
                    <div class="flex flex-wrap items-center gap-2">
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
            @update:per-page="applyFilters"
        />
    </StoreAdminLayout>
</template>
