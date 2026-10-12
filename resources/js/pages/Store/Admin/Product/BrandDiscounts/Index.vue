<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import {
    index as brandDiscountsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BrandDiscountController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreBrandDiscountFormFields from '@/components/StoreBrandDiscountFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { filterApplyButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreBrand } from '@/types/store';

const props = defineProps<{
    brands: Paginated<StoreBrand>;
    filters: { per_page: number; search: string | null };
}>();
const access = useStoreAccess();
const search = ref(props.filters.search ?? '');
const confirming = ref<number | null>(null);
function filter(perPage = props.filters.per_page): void {
    router.get(
        brandDiscountsIndex().url,
        { search: search.value, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head :title="t('store.brandDiscount.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.brandDiscount.title')" />
        <form class="mb-4 flex items-end gap-3" @submit.prevent="filter()">
            <FormField
                id="search"
                :value="search"
                @input="search = ($event.target as HTMLInputElement).value"
                maxlength="200"
                :label="t('store.brandDiscount.search')"
            />
            <SubmitButton
                :label="t('common.filters.apply')"
                :processing-label="t('common.filters.apply')"
                :processing="false"
            />
        </form>
        <DataTable
            :columns="[
                t('store.brandDiscount.logo'),
                t('store.brandDiscount.name'),
                t('store.brandDiscount.action'),
            ]"
            :rows="brands.data"
            :empty-message="t('store.brandDiscount.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <img
                        v-if="row.logo_url"
                        :src="row.logo_url"
                        :alt="row.name"
                        class="h-10 w-10 object-contain"
                    /><span v-else>—</span>
                </td>
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2">
                    <Form
                        v-if="access.can('product_edit')"
                        :id="`brand-discount-${row.id}`"
                        v-slot="{ errors, processing }"
                        :action="update({ mergeQuery: {} })"
                        :options="{ preserveScroll: true }"
                        @success="confirming = null"
                    >
                        <StoreBrandDiscountFormFields
                            :brand-id="row.id"
                            :errors="errors"
                        />
                        <FieldError :message="errors.brand_id" />
                        <button
                            type="button"
                            :class="filterApplyButtonClass"
                            class="disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="processing"
                            @click="confirming = row.id"
                        >
                            <Check class="h-4 w-4" />{{
                                t('store.brandDiscount.set')
                            }}
                        </button>
                        <FilterDialog
                            :open="confirming === row.id"
                            :title="t('store.brandDiscount.confirmTitle')"
                            :close-label="t('common.close')"
                            @update:open="confirming = $event ? row.id : null"
                        >
                            <p class="mb-4 flex items-center gap-2">
                                <TriangleAlert class="h-5 w-5 shrink-0" />{{
                                    t('store.brandDiscount.confirmMessage', {
                                        brand: row.name,
                                    })
                                }}
                            </p>
                            <template #footer
                                ><SubmitButton
                                    :form="`brand-discount-${row.id}`"
                                    :label="t('store.brandDiscount.confirm')"
                                    :processing-label="
                                        t('store.brandDiscount.saving')
                                    "
                                    :processing="processing"
                            /></template>
                        </FilterDialog>
                    </Form>
                    <span v-else>{{ t('store.brandDiscount.readOnly') }}</span>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="brands.meta.links"
            :per-page="filters.per_page"
            @update:per-page="filter"
        />
    </StoreAdminLayout>
</template>
