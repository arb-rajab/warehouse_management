<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    index as campaignIndex,
    create,
    destroy,
    edit,
    update_status,
    update_featured,
} from '@/actions/App/Http/Controllers/Store/Admin/FlashDealController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreFlashDeal, StoreCampaignFilters } from '@/types/store';
const props = defineProps<{
    deals: Paginated<StoreFlashDeal>;
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
</script>
<template>
    <Head :title="t('store.flashDeals.index.title')" /><StoreAdminLayout
        ><PageHeader :title="t('store.flashDeals.index.title')"
            ><AddResourceLink
                v-if="access.can('add_flash_deal')"
                :href="create()"
                :label="t('store.flashDeals.index.add')"
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
        <DataTable
            :columns="
                [
                    'title',
                    'startDate',
                    'endDate',
                    'active',
                    'featured',
                    'actions',
                ].map((k) => t(`store.campaigns.fields.${k}`))
            "
            :rows="deals.data"
            :empty-message="t('store.campaigns.fields.empty')"
            ><template #row="{ row }"
                ><td class="px-4 py-2">{{ row.title }}</td>
                <td class="px-4 py-2">{{ row.start_date }}</td>
                <td class="px-4 py-2">{{ row.end_date }}</td>
                <td class="px-4 py-2">
                    <Form
                        v-if="access.can('edit_flash_deal')"
                        :action="update_status(row, { mergeQuery: {} })"
                        #default="{ processing }"
                        ><input
                            type="hidden"
                            name="value"
                            :value="row.status ? '0' : '1'" /><SubmitButton
                            :label="
                                t(
                                    row.status
                                        ? 'store.campaigns.disable'
                                        : 'store.campaigns.enable',
                                )
                            "
                            :processing-label="t('store.campaigns.saving')"
                            :processing="processing"
                            :aria-label="
                                t('store.campaigns.toggle', {
                                    field: t('store.campaigns.fields.active'),
                                    title: row.title,
                                })
                            " /></Form
                    ><span v-else
                        >{t(row.status?'store.campaigns.enabled':'store.campaigns.disabled')}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <Form
                        v-if="access.can('publish_flash_deal')"
                        :action="update_featured(row, { mergeQuery: {} })"
                        #default="{ processing }"
                        ><input
                            type="hidden"
                            name="value"
                            :value="row.featured ? '0' : '1'" /><SubmitButton
                            :label="
                                t(
                                    row.featured
                                        ? 'store.campaigns.disable'
                                        : 'store.campaigns.enable',
                                )
                            "
                            :processing-label="t('store.campaigns.saving')"
                            :processing="processing"
                            :aria-label="
                                t('store.campaigns.toggle', {
                                    field: t('store.campaigns.fields.featured'),
                                    title: row.title,
                                })
                            " /></Form
                    ><span v-else
                        >{t(row.featured?'store.campaigns.enabled':'store.campaigns.disabled')}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_flash_deal')"
                            :href="edit(row)"
                            ><Pencil class="size-4" />{{
                                t('store.campaigns.edit')
                            }}</TableActionLink
                        ><TableActionLink
                            v-if="access.can('delete_flash_deal')"
                            :href="destroy(row, { mergeQuery: {} })"
                            method="delete"
                            as="button"
                            variant="danger"
                            :on-before="() => confirmDelete(row.title)"
                            ><Trash2 class="size-4" />{{
                                t('store.campaigns.delete')
                            }}</TableActionLink
                        ><a
                            :href="row.public_url"
                            :class="filterTriggerButtonClass"
                            >{{ t('store.campaigns.viewPublic') }}</a
                        >
                    </div>
                </td></template
            ></DataTable
        ><Pagination
            :links="deals.meta.links"
            :per-page="filters.per_page"
            @update:per-page="perPage"
    /></StoreAdminLayout>
</template>
