<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    bulkDestroy,
    create,
    destroy,
    edit,
    index as popupsIndex,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/DynamicPopupController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
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
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreDynamicPopupSummary } from '@/types/store';

const props = defineProps<{
    popups: Paginated<StoreDynamicPopupSummary>;
    filters: PerPageFilters & { search?: string | null };
}>();
const access = useStoreAccess();
const selected = ref<number[]>([]);
const perPage = computed(() => props.filters.per_page ?? 20);
const allSelected = computed(
    () =>
        props.popups.data.length > 0 &&
        props.popups.data.every((popup) => selected.value.includes(popup.id)),
);
watch(
    () => props.popups.data.map((popup) => popup.id).join(','),
    () => {
        selected.value = [];
    },
);
function changePerPage(size: number): void {
    router.get(
        popupsIndex.url(),
        { per_page: size, search: props.filters.search },
        { preserveState: true, replace: true },
    );
}
function confirmPublication(published: boolean): boolean {
    return confirm(
        t(
            published
                ? 'store.dynamicPopups.index.confirmHide'
                : 'store.dynamicPopups.index.confirmPublish',
        ),
    );
}
function selectAll(checked: boolean): void {
    selected.value = checked ? props.popups.data.map((popup) => popup.id) : [];
}
const columns = useTableColumns({
    listId: 'marketing.dynamicPopups',
    columns: [
        { key: 'image', label: 'store.dynamicPopups.index.image' },
        {
            key: 'title',
            label: 'store.dynamicPopups.fields.title',
            required: true,
        },
        { key: 'link', label: 'store.dynamicPopups.index.link' },
        { key: 'status', label: 'store.dynamicPopups.index.status' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...(access.value.can('delete_dynamic_popups')
        ? [t('store.dynamicPopups.index.select')]
        : []),
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.dynamicPopups.index.actions'),
]);
</script>

<template>
    <Head :title="t('store.dynamicPopups.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.dynamicPopups.index.title')">
            <AddResourceLink
                v-if="access.can('add_dynamic_popups')"
                :href="create()"
                :label="t('store.dynamicPopups.index.add')"
            />
        </PageHeader>
        <Form
            :action="popupsIndex()"
            #default="{ errors, processing }"
            class="mb-4 flex items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="perPage" />
            <FormField
                id="search"
                :label="t('store.dynamicPopups.index.search')"
                :value="filters.search ?? ''"
                :error="errors.search"
                maxlength="255"
            />
            <SubmitButton
                :label="t('store.dynamicPopups.index.search')"
                :processing-label="t('store.dynamicPopups.index.searching')"
                :processing="processing"
            />
        </Form>
        <StoreColumnPicker :state="columns">
            <Form
                v-if="access.can('delete_dynamic_popups')"
                :action="bulkDestroy({ mergeQuery: {} })"
                :on-before="
                    () =>
                        selected.length > 0 &&
                        confirmDelete(
                            t('store.dynamicPopups.index.selection', {
                                count: selected.length,
                            }),
                        )
                "
                #default="{ errors, processing }"
                class="max-w-sm"
                @success="selected = []"
            >
                <input
                    v-for="id in selected"
                    :key="id"
                    type="hidden"
                    name="id[]"
                    :value="id"
                />
                <SubmitButton
                    :label="t('store.dynamicPopups.index.deleteSelection')"
                    :processing-label="t('store.dynamicPopups.index.deleting')"
                    :processing="processing"
                    :disabled="processing || selected.length === 0"
                />
                <FieldError :message="errors.id" />
            </Form>
        </StoreColumnPicker>
        <DataTable
            :columns="tableColumns"
            :rows="popups.data"
            :empty-message="t('store.dynamicPopups.index.empty')"
        >
            <template #row="{ row: popup }">
                <td
                    v-if="access.can('delete_dynamic_popups')"
                    class="px-4 py-2"
                >
                    <input
                        v-model="selected"
                        type="checkbox"
                        :value="popup.id"
                        :aria-label="
                            t('store.dynamicPopups.index.selectPopup', {
                                title: popup.title,
                            })
                        "
                        class="cursor-pointer"
                    />
                </td>
                <td v-if="shown.image" class="px-4 py-2">
                    <img
                        v-if="popup.banner_url"
                        :src="popup.banner_url"
                        alt=""
                        class="size-16 rounded object-cover"
                    />
                </td>
                <td class="max-w-xs truncate px-4 py-2" :title="popup.title">
                    {{ popup.title }}
                </td>
                <td v-if="shown.link" class="px-4 py-2 break-all">
                    {{ popup.btn_link }}
                </td>
                <td v-if="shown.status" class="px-4 py-2">
                    <StoreStatusSwitch
                        v-if="access.can('publish_dynamic_popups')"
                        :action="updateStatus({ mergeQuery: {} })"
                        :checked="popup.status"
                        :label="
                            t('store.dynamicPopups.index.toggle', {
                                title: popup.title,
                            })
                        "
                        :state-labels="{
                            on: t('store.dynamicPopups.index.published'),
                            off: t('store.dynamicPopups.index.hidden'),
                        }"
                        :on-before="() => confirmPublication(popup.status)"
                        ><input type="hidden" name="id" :value="popup.id"
                    /></StoreStatusSwitch>
                    <span v-else>{{
                        popup.status
                            ? t('store.dynamicPopups.index.published')
                            : t('store.dynamicPopups.index.hidden')
                    }}</span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink
                            v-if="access.can('edit_dynamic_popups')"
                            :href="edit(popup)"
                            ><Pencil class="size-3.5" />{{
                                t('store.dynamicPopups.index.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_dynamic_popups')"
                            :href="destroy(popup, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(popup.title)"
                            ><Trash2 class="size-3.5" />{{
                                t('store.dynamicPopups.index.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <label
            v-if="access.can('delete_dynamic_popups') && popups.data.length"
            class="mt-3 flex cursor-pointer items-center gap-2"
        >
            <input
                type="checkbox"
                :checked="allSelected"
                class="cursor-pointer"
                @change="selectAll(($event.target as HTMLInputElement).checked)"
            />{{ t('store.dynamicPopups.index.selectAll') }}
        </label>
        <Pagination
            :links="popups.meta.links"
            :per-page="perPage"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
