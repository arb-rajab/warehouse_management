<script setup lang="ts">
import { SlidersHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import {
    countActiveCellHighlightFilters,
    emptyCellHighlightFilters,
} from '@/lib/cellHighlight';
import type { CellHighlightFiltersValue } from '@/lib/cellHighlight';
import { selectedCountLabel } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';

defineProps<{
    products: { id: number; name: string }[];
}>();

// The parent must pass a `reactive()` object, not a plain value — every
// nested field here is mutated in place (v-model on `filters.state` etc.,
// and `clear()`'s Object.assign) rather than reassigning `filters.value`, so
// no `update:modelValue` is ever emitted; the parent sees changes because
// it's the same reactive object, not because of an event round-trip.
const filters = defineModel<CellHighlightFiltersValue>({ required: true });

const cellStates: Cell['state'][] = ['empty', 'full', 'opened'];

function stateLabel(state: Cell['state']): string {
    return t(`cellLog.states.${state}`);
}

const open = ref(false);

const activeCount = computed(() =>
    countActiveCellHighlightFilters(filters.value),
);

function clear(): void {
    Object.assign(filters.value, emptyCellHighlightFilters());
}
</script>

<template>
    <div>
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
            @click="open = true"
        >
            <SlidersHorizontal class="h-4 w-4" />
            {{ t('rows.show.highlight.button') }}
            <span
                v-if="activeCount > 0"
                class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-gray-900 px-1 text-xs font-medium text-white dark:bg-white dark:text-gray-900"
            >
                {{ activeCount }}
            </span>
        </button>

        <FilterDialog
            v-model:open="open"
            :title="t('rows.show.highlight.button')"
            :close-label="t('cellLog.filters.close')"
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FilterMultiSelect
                    id="highlight-state"
                    v-model="filters.state"
                    :label="t('cells.filters.state')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :options="
                        cellStates.map((state) => ({
                            value: state,
                            label: stateLabel(state),
                        }))
                    "
                />

                <div>
                    <label
                        for="highlight-expires-within-days"
                        class="mb-1 block text-sm text-gray-700 dark:text-neutral-300"
                        >{{ t('cellHighlight.expiresWithinDays') }}</label
                    >
                    <input
                        id="highlight-expires-within-days"
                        v-model="filters.expiresWithinDays"
                        type="number"
                        min="1"
                        step="1"
                        :placeholder="
                            t('cellHighlight.expiresWithinDaysPlaceholder')
                        "
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                </div>

                <FilterMultiSelect
                    id="highlight-product"
                    v-model="filters.productIds"
                    :label="t('cellLog.filters.product')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :options="
                        products.map((product) => ({
                            value: product.id.toString(),
                            label: product.name,
                        }))
                    "
                />

                <div>
                    <label
                        for="highlight-stale-after-days"
                        class="mb-1 block text-sm text-gray-700 dark:text-neutral-300"
                        >{{ t('cellHighlight.staleAfterDays') }}</label
                    >
                    <input
                        id="highlight-stale-after-days"
                        v-model="filters.staleAfterDays"
                        type="number"
                        min="1"
                        step="1"
                        :placeholder="
                            t('cellHighlight.staleAfterDaysPlaceholder')
                        "
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                </div>
            </div>

            <div
                class="mt-6 flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
            >
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                    @click="clear"
                >
                    {{ t('cellLog.filters.clear') }}
                </button>
            </div>
        </FilterDialog>
    </div>
</template>
