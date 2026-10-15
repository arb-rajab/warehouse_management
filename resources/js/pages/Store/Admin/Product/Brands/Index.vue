<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    edit,
    index as brandsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/BrandController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreBrandFormFields from '@/components/StoreBrandFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreBrand } from '@/types/store';
const props = defineProps<{
    brands: Paginated<StoreBrand>;
    filters: PerPageFilters & { search: string | null };
    defaultLanguage: string;
}>();
const access = useStoreAccess();
const createdBrands = ref(0);
function onPerPageChange(perPage: number): void {
    router.get(
        brandsIndex().url,
        { search: props.filters.search ?? '', per_page: perPage },
        { preserveState: true, replace: true },
    );
}
function search(event: Event): void {
    const data = new FormData(event.target as HTMLFormElement);
    router.get(
        brandsIndex().url,
        {
            search: String(data.get('search') ?? ''),
            per_page: props.filters.per_page ?? 20,
        },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head :title="t('store.brands.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.brands.title')" />
        <form @submit.prevent="search" class="mb-4 max-w-sm">
            <FormField
                id="search"
                :label="t('store.brands.search')"
                :value="filters.search ?? ''"
                maxlength="255"
            />
        </form>
        <DataTable
            :columns="[
                t('store.brands.name'),
                t('store.brands.logo'),
                t('store.brands.actions'),
            ]"
            :rows="brands.data"
            :empty-message="t('store.brands.empty')"
        >
            <template #row="{ row: item }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{ item.name }}
                </td>
                <td class="px-4 py-2">
                    <img
                        v-if="item.logo_url"
                        :src="item.logo_url"
                        :alt="t('store.brands.logo')"
                        class="h-12 w-auto object-contain"
                    />
                    <span v-else>—</span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_brand')"
                            :href="
                                edit(item, { query: { lang: defaultLanguage } })
                            "
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.brands.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_brand')"
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
            :links="brands.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
        <section v-if="access.can('add_brand')" class="mt-6 max-w-xl space-y-4">
            <h2 class="text-lg font-semibold">
                {{ t('store.brands.create') }}
            </h2>
            <Form
                :action="store()"
                reset-on-success
                @success="createdBrands++"
                #default="{ errors, processing }"
                class="space-y-4"
            >
                <StoreBrandFormFields :key="createdBrands" :errors="errors" />
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
        </section>
    </StoreAdminLayout>
</template>
