<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    index as reviewsIndex,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ReviewController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';

import type { StoreReview } from '@/types/store';

const props = defineProps<{
    reviews: Paginated<StoreReview>;
    filters: { per_page: number; rating: string | null };
}>();
const access = useStoreAccess();
const rating = ref(props.filters.rating ?? '');
const currentPerPage = computed(() => props.filters.per_page);
function filter(perPage = currentPerPage.value): void {
    router.get(
        reviewsIndex().url,
        { rating: rating.value, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.reviews.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.reviews.title')" />
        <form
            class="mb-4 flex flex-wrap items-end gap-3"
            @submit.prevent="filter()"
        >
            <label>
                {{ t('store.reviews.filterRating') }}
                <select
                    v-model="rating"
                    name="rating"
                    :class="plainFieldInputClass"
                >
                    <option value="">{{ t('store.reviews.newest') }}</option>
                    <option value="rating,desc">
                        {{ t('store.reviews.highRating') }}
                    </option>
                    <option value="rating,asc">
                        {{ t('store.reviews.lowRating') }}
                    </option>
                </select>
            </label>
            <SubmitButton
                :label="t('common.filters.apply')"
                :processing-label="t('common.filters.apply')"
                :processing="false"
            />
        </form>
        <DataTable
            :rows="reviews.data"
            :columns="[
                t('store.reviews.product'),
                t('store.reviews.owner'),
                t('store.reviews.customer'),
                t('store.reviews.rating'),
                t('store.reviews.comment'),
                t('store.reviews.published'),
            ]"
            :empty-message="t('store.reviews.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <a
                        :href="row.product.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="underline"
                        >{{ row.product.name }}</a
                    >
                </td>
                <td class="px-4 py-2">{{ row.product.owner }}</td>
                <td class="px-4 py-2">
                    {{ row.customer.name }} ({{ row.customer.email }})
                </td>
                <td class="px-4 py-2">{{ row.rating }}</td>
                <td class="px-4 py-2 whitespace-pre-wrap">{{ row.comment }}</td>
                <td class="px-4 py-2">
                    <Form
                        v-if="access.can('publish_product_review')"
                        v-slot="{ processing, errors }"
                        :action="updatePublished(row, { mergeQuery: {} })"
                        :options="{ preserveScroll: true }"
                    >
                        <input
                            type="hidden"
                            name="status"
                            :value="row.published ? 0 : 1"
                        />
                        <SubmitButton
                            :label="
                                t(
                                    row.published
                                        ? 'store.reviews.unpublish'
                                        : 'store.reviews.publish',
                                )
                            "
                            :processing-label="t('store.reviews.saving')"
                            :processing="processing"
                        />
                        <FieldError :message="errors.status" />
                    </Form>
                    <span v-else>{{
                        t(
                            row.published
                                ? 'store.reviews.yes'
                                : 'store.reviews.no',
                        )
                    }}</span>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="reviews.meta.links"
            :per-page="currentPerPage"
            @update:per-page="filter"
        />
    </StoreAdminLayout>
</template>
