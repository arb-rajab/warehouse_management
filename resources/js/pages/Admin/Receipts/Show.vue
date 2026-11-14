<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, FileX, Info } from '@lucide/vue';
import { computed } from 'vue';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as receiptsIndex } from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { show as showUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import DataTable from '@/components/DataTable.vue';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import HelpLink from '@/components/HelpLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProductOptionLabel from '@/components/ProductOptionLabel.vue';
import ReceiptFileTile from '@/components/ReceiptFileTile.vue';
import ReceiptStatusBadge from '@/components/ReceiptStatusBadge.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { productName } from '@/lib/productName';
import type { ReceiptDetail } from '@/types/admin';

const props = defineProps<{
    receipt: ReceiptDetail;
}>();

/**
 * The pallets as written, in the order typed. A pallet entry has no id of its
 * own in the payload, so the table's row key is its position on the bill: the
 * number shown in the first column, never a pallet id.
 */
const palletRows = computed(() =>
    props.receipt.pallets.map((pallet, index) => ({
        ...pallet,
        id: index + 1,
    })),
);
</script>

<template>
    <Head :title="t('receipts.show.title', { id: receipt.id })" />

    <AdminLayout>
        <PageHeader :title="t('receipts.show.title', { id: receipt.id })">
            <HelpLink :href="showHelp('receipts')" />
        </PageHeader>

        <TableLink :href="receiptsIndex()" class="mb-4 inline-flex">
            <ArrowLeft class="h-3.5 w-3.5 shrink-0 rtl:rotate-180" />
            {{ t('receipts.show.backToList') }}
        </TableLink>

        <DetailList
            :title="t('receipts.show.details')"
            :columns="3"
            class="mb-6"
        >
            <DetailItem
                :label="t('receipts.show.warehouse')"
                :value="receipt.warehouse.name"
            />
            <DetailItem :label="t('receipts.show.status')">
                <ReceiptStatusBadge :status="receipt.status" />
            </DetailItem>
            <DetailItem :label="t('receipts.show.createdBy')">
                <TableLink :href="showUser(receipt.created_by)">
                    {{ receipt.created_by.name }}
                </TableLink>
            </DetailItem>
            <DetailItem
                :label="t('receipts.show.createdAt')"
                :value="formatDateTime(receipt.created_at)"
            />
            <DetailItem :label="t('receipts.show.finalizedBy')">
                <TableLink
                    v-if="receipt.finalized_by"
                    :href="showUser(receipt.finalized_by)"
                >
                    {{ receipt.finalized_by.name }}
                </TableLink>
            </DetailItem>
            <DetailItem
                :label="t('receipts.show.finalizedAt')"
                :value="
                    receipt.finalized_at
                        ? formatDateTime(receipt.finalized_at)
                        : null
                "
            />
            <DetailItem
                :label="t('receipts.show.note')"
                :value="receipt.note"
                full
                preserve
            />
        </DetailList>

        <section class="mb-6">
            <h2 :class="sectionHeadingClass">
                {{ t('receipts.show.files', { count: receipt.files.length }) }}
            </h2>
            <ul
                v-if="receipt.files.length > 0"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <ReceiptFileTile
                    v-for="file in receipt.files"
                    :key="file.id"
                    :file="file"
                />
            </ul>
            <p v-else class="flex items-center gap-2 text-sm text-muted">
                <FileX class="h-4 w-4 shrink-0" />
                {{ t('receipts.show.noFiles') }}
            </p>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{
                    t('receipts.show.pallets', {
                        count: receipt.pallets.length,
                    })
                }}
            </h2>
            <p
                v-if="receipt.status === 'draft'"
                role="status"
                class="mb-3 flex items-start gap-2 rounded-lg border border-info/30 bg-info-soft px-3 py-2 text-sm text-info-strong"
            >
                <Info class="mt-0.5 h-4 w-4 shrink-0" />
                {{ t('receipts.show.draftHint') }}
            </p>
            <DataTable
                :columns="[
                    t('receipts.show.columns.number'),
                    t('receipts.show.columns.product'),
                    t('receipts.show.columns.boxes'),
                    t('receipts.show.columns.expiration'),
                    t('receipts.show.columns.note'),
                ]"
                :rows="palletRows"
                :empty-message="t('receipts.show.palletsEmpty')"
            >
                <template #row="{ row }">
                    <td class="px-4 py-2 font-mono text-muted">{{ row.id }}</td>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                            <img
                                v-if="row.product.image_url"
                                :src="row.product.image_url"
                                :alt="
                                    productName(
                                        row.product.name,
                                        row.product.ar_name,
                                    )
                                "
                                class="h-8 w-8 shrink-0 rounded object-cover"
                            />
                            <ProductOptionLabel :product="row.product" />
                        </div>
                    </td>
                    <td class="px-4 py-2">{{ row.boxes }}</td>
                    <td class="px-4 py-2">
                        {{
                            row.expiration_date
                                ? formatDate(row.expiration_date)
                                : '—'
                        }}
                    </td>
                    <td
                        class="max-w-xs truncate px-4 py-2 text-muted"
                        :title="row.note ?? undefined"
                        data-testid="receipt-entry-note"
                    >
                        {{ row.note ?? '—' }}
                    </td>
                </template>
            </DataTable>
        </section>
    </AdminLayout>
</template>
