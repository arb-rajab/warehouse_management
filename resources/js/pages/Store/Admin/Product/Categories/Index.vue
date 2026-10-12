<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    create,
    destroy,
    edit,
    index as categoriesIndex,
    updateFeatured,
} from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreCategory } from '@/types/store';
const props = defineProps<{
    categories: Paginated<StoreCategory>;
    filters: PerPageFilters & { search: string | null };
    defaultLanguage: string;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        categoriesIndex().url,
        { search: props.filters.search ?? '', per_page: perPage },
        { preserveState: true, replace: true },
    );
}
function search(event: Event): void {
    const data = new FormData(event.target as HTMLFormElement);
    router.get(
        categoriesIndex().url,
        {
            search: String(data.get('search') ?? ''),
            per_page: props.filters.per_page ?? 20,
        },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head :title="t('store.categories.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.categories.title')">
            <AddResourceLink
                v-if="access.can('add_product_category')"
                :href="create()"
                :label="t('store.categories.create')"
            />
        </PageHeader>
        <form @submit.prevent="search" class="mb-4 max-w-sm">
            <FormField
                id="search"
                :label="t('store.categories.search')"
                :value="filters.search ?? ''"
                maxlength="255"
            />
        </form>
        <DataTable
            :columns="[
                t('store.categories.name'),
                t('store.categories.parent'),
                t('store.categories.ordering'),
                t('store.categories.level'),
                t('store.categories.banner'),
                t('store.categories.icon'),
                t('store.categories.cover'),
                t('store.categories.featured'),
                t('store.categories.commission'),
                t('store.categories.actions'),
            ]"
            :rows="categories.data"
            :empty-message="t('store.categories.empty')"
        >
            <template #row="{ row: item }">
                <td class="px-4 py-2">{{ item.name }}</td>
                <td class="px-4 py-2">
                    <TableLink
                        v-if="
                            item.parent && access.can('edit_product_category')
                        "
                        :href="
                            edit(item.parent, {
                                query: { lang: defaultLanguage },
                            })
                        "
                        >{{ item.parent.name }}</TableLink
                    >
                    <span v-else>{{ item.parent?.name ?? '—' }}</span>
                </td>
                <td class="px-4 py-2">{{ item.order_level }}</td>
                <td class="px-4 py-2">{{ item.level }}</td>
                <td
                    v-for="(url, field) in {
                        banner: item.banner_url,
                        icon: item.icon_url,
                        cover: item.cover_image_url,
                    }"
                    :key="field"
                    class="px-4 py-2"
                >
                    <img
                        v-if="url"
                        :src="url"
                        :alt="t(`store.categories.${field}`)"
                        class="h-12 w-12 object-contain"
                    />
                    <span v-else>—</span>
                </td>
                <td class="px-4 py-2">
                    <StoreStatusButton
                        v-if="access.can('edit_product_category')"
                        :action="updateFeatured({ mergeQuery: {} })"
                        :active="item.featured"
                        :label="t('store.categories.featured')"
                    >
                        <input type="hidden" name="id" :value="item.id" />
                    </StoreStatusButton>
                    <span v-else>{{
                        t(
                            item.featured
                                ? 'store.reference.enabled'
                                : 'store.reference.disabled',
                        )
                    }}</span>
                </td>
                <td class="px-4 py-2">{{ item.commision_rate }} %</td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_product_category')"
                            :href="
                                edit(item, { query: { lang: defaultLanguage } })
                            "
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.categories.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_product_category')"
                            :href="destroy(item, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(item.name)"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.reference.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="categories.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
