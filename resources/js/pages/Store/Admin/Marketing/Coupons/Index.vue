<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    destroy,
    edit,
    index as couponIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreCoupon } from '@/types/store';

defineProps<{ coupons: Paginated<StoreCoupon>; filters: PerPageFilters }>();
const access = useStoreAccess();

function onPerPageChange(perPage: number): void {
    router.get(
        couponIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'marketing.coupons',
    columns: [
        { key: 'code', label: 'store.coupons.index.code', required: true },
        { key: 'type', label: 'store.coupons.index.type' },
        { key: 'discount', label: 'store.coupons.index.discount' },
        { key: 'startDate', label: 'store.coupons.index.startDate' },
        { key: 'endDate', label: 'store.coupons.index.endDate' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.coupons.index.actions'),
]);
</script>

<template>
    <Head :title="t('store.coupons.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.coupons.index.title')">
            <AddResourceLink
                v-if="access.can('add_coupon')"
                :href="create()"
                :label="t('store.coupons.index.add')"
            />
        </PageHeader>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="coupons.data"
            :empty-message="t('store.coupons.index.empty')"
        >
            <template #row="{ row: coupon }">
                <td class="px-4 py-2 font-mono">{{ coupon.code }}</td>
                <td v-if="shown.type" class="px-4 py-2">
                    {{
                        t(
                            `store.coupons.fields.${coupon.type === 'product_base' ? 'product' : 'cart'}`,
                        )
                    }}
                </td>
                <td v-if="shown.discount" class="px-4 py-2">
                    {{ coupon.discount }}
                    {{
                        coupon.discount_type === 'percent'
                            ? '%'
                            : t('store.coupons.fields.amount')
                    }}
                </td>
                <td v-if="shown.startDate" class="px-4 py-2">
                    {{ coupon.start_date }}
                </td>
                <td v-if="shown.endDate" class="px-4 py-2">
                    {{ coupon.end_date }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_coupon')"
                            :href="edit(coupon)"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.coupons.index.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_coupon')"
                            :href="destroy(coupon, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(coupon.code)"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.coupons.index.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="coupons.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
