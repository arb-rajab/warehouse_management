<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
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
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
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
        <DataTable
            :columns="
                [
                    'code',
                    'type',
                    'discount',
                    'startDate',
                    'endDate',
                    'actions',
                ].map((key) => t(`store.coupons.index.${key}`))
            "
            :rows="coupons.data"
            :empty-message="t('store.coupons.index.empty')"
        >
            <template #row="{ row: coupon }">
                <td class="px-4 py-2">{{ coupon.code }}</td>
                <td class="px-4 py-2">
                    {{
                        t(
                            `store.coupons.fields.${coupon.type === 'product_base' ? 'product' : 'cart'}`,
                        )
                    }}
                </td>
                <td class="px-4 py-2">
                    {{ coupon.discount }}
                    {{
                        coupon.discount_type === 'percent'
                            ? '%'
                            : t('store.coupons.fields.amount')
                    }}
                </td>
                <td class="px-4 py-2">{{ coupon.start_date }}</td>
                <td class="px-4 py-2">{{ coupon.end_date }}</td>
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
