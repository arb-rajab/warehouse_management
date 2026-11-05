<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    index as campaignIndex,
    create,
    destroy,
    edit,
    update_status,
    update_repeatable,
    update_multiple_usage,
    brandCreate,
} from '@/actions/App/Http/Controllers/Store/Admin/OfferController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { StoreOffer, StoreCampaignFilters } from '@/types/store';
const props = defineProps<{
    offers: Paginated<StoreOffer>;
    filters: StoreCampaignFilters;
}>();
const access = useStoreAccess();
function perPage(value: number) {
    router.get(
        campaignIndex.url(),
        { per_page: value, search: props.filters.search },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'marketing.offers',
    columns: [
        { key: 'title', label: 'store.campaigns.fields.title', required: true },
        { key: 'startDate', label: 'store.campaigns.fields.startDate' },
        { key: 'endDate', label: 'store.campaigns.fields.endDate' },
        { key: 'active', label: 'store.campaigns.fields.active' },
        { key: 'repeatable', label: 'store.campaigns.fields.repeatable' },
        { key: 'multipleUsage', label: 'store.campaigns.fields.multipleUsage' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.campaigns.fields.actions'),
]);
</script>
<template>
    <Head :title="t('store.offers.index.title')" /><StoreAdminLayout
        ><PageHeader :title="t('store.offers.index.title')"
            ><AddResourceLink
                v-if="access.can('add_offer_deal')"
                :href="create()"
                :label="t('store.offers.index.add')" /><AddResourceLink
                v-if="access.can('add_offer_deal')"
                :href="brandCreate()"
                :label="t('store.offers.index.addBrand')"
        /></PageHeader>
        <Form
            :action="campaignIndex()"
            #default="{ errors, processing }"
            class="mb-4 flex items-end gap-2"
            ><FormField
                id="search"
                type="search"
                maxlength="255"
                :value="filters.search ?? ''"
                :label="t('store.campaigns.search')"
                :error="errors.search" /><input
                type="hidden"
                name="per_page"
                :value="filters.per_page" /><SubmitButton
                :label="t('store.campaigns.search')"
                :processing-label="t('store.campaigns.search')"
                :processing="processing"
        /></Form>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="offers.data"
            :empty-message="t('store.campaigns.fields.empty')"
            ><template #row="{ row }"
                ><td class="max-w-xs truncate px-4 py-2" :title="row.title">
                    {{ row.title }}
                </td>
                <td v-if="shown.startDate" class="px-4 py-2">
                    {{ row.start_date }}
                </td>
                <td v-if="shown.endDate" class="px-4 py-2">
                    {{ row.end_date }}
                </td>
                <td v-if="shown.active" class="px-4 py-2">
                    <StoreStatusSwitch
                        v-if="access.can('edit_offer_deal')"
                        :action="update_status(row, { mergeQuery: {} })"
                        :checked="row.is_active"
                        input-name="value"
                        :label="
                            t('store.campaigns.toggle', {
                                field: t('store.campaigns.fields.active'),
                                title: row.title,
                            })
                        "
                    /><span v-else>{{
                        t(
                            row.is_active
                                ? 'store.campaigns.enabled'
                                : 'store.campaigns.disabled',
                        )
                    }}</span>
                </td>
                <td v-if="shown.repeatable" class="px-4 py-2">
                    <StoreStatusSwitch
                        v-if="access.can('edit_offer_deal')"
                        :action="update_repeatable(row, { mergeQuery: {} })"
                        :checked="row.is_repeatable"
                        input-name="value"
                        :label="
                            t('store.campaigns.toggle', {
                                field: t('store.campaigns.fields.repeatable'),
                                title: row.title,
                            })
                        "
                    /><span v-else>{{
                        t(
                            row.is_repeatable
                                ? 'store.campaigns.enabled'
                                : 'store.campaigns.disabled',
                        )
                    }}</span>
                </td>
                <td v-if="shown.multipleUsage" class="px-4 py-2">
                    <StoreStatusSwitch
                        v-if="access.can('edit_offer_deal')"
                        :action="update_multiple_usage(row, { mergeQuery: {} })"
                        :checked="row.multiple_usage"
                        input-name="value"
                        :label="
                            t('store.campaigns.toggle', {
                                field: t(
                                    'store.campaigns.fields.multipleUsage',
                                ),
                                title: row.title,
                            })
                        "
                    /><span v-else>{{
                        t(
                            row.multiple_usage
                                ? 'store.campaigns.enabled'
                                : 'store.campaigns.disabled',
                        )
                    }}</span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex flex-nowrap items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_offer_deal')"
                            :href="edit(row)"
                            ><Pencil class="size-4" />{{
                                t('store.campaigns.edit')
                            }}</TableActionLink
                        ><TableActionLink
                            v-if="access.can('delete_offer_deal')"
                            :href="destroy(row, { mergeQuery: {} })"
                            method="delete"
                            as="button"
                            variant="danger"
                            :on-before="() => confirmDelete(row.title)"
                            ><Trash2 class="size-4" />{{
                                t('store.campaigns.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td></template
            ></DataTable
        ><Pagination
            :links="offers.meta.links"
            :per-page="filters.per_page"
            @update:per-page="perPage"
    /></StoreAdminLayout>
</template>
