<script setup lang="ts">
import { SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import FilterCheckbox from '@/components/FilterCheckbox.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import {
    countActiveCellHighlightFilters,
    emptyCellHighlightFilters,
} from '@/lib/cellHighlight';
import type { CellHighlightFiltersValue } from '@/lib/cellHighlight';
import { CELL_STATES, cellStateLabel } from '@/lib/cellStateColor';
import {
    countBadgeClass,
    filterClearButtonClass,
    filterTriggerButtonClass,
    selectedCountLabel,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

defineProps<{
    products: { id: number; name: string }[];
}>();

// The parent must pass a `reactive()` object, not a plain value — every
// nested field here is mutated in place (v-model on `filters.state` etc.,
// and `clear()`'s Object.assign) rather than reassigning `filters.value`, so
// no `update:modelValue` is ever emitted; the parent sees changes because
// it's the same reactive object, not because of an event round-trip.
const filters = defineModel<CellHighlightFiltersValue>({ required: true });

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
            :class="filterTriggerButtonClass"
            @click="open = true"
        >
            <SlidersHorizontal class="h-4 w-4" />
            {{ t('rows.show.highlight.button') }}
            <span v-if="activeCount > 0" :class="countBadgeClass">
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
                        CELL_STATES.map((state) => ({
                            value: state,
                            label: cellStateLabel(state),
                        }))
                    "
                />

                <FilterNumberField
                    id="highlight-expires-within-days"
                    v-model="filters.expiresWithinDays"
                    :label="t('cellHighlight.expiresWithinDays')"
                    :placeholder="
                        t('cellHighlight.expiresWithinDaysPlaceholder')
                    "
                />

                <FilterCheckbox
                    id="highlight-expired"
                    v-model="filters.expired"
                    :label="t('cellHighlight.expired')"
                />

                <FilterCheckbox
                    id="highlight-inactive"
                    v-model="filters.inactive"
                    :label="t('cellHighlight.inactive')"
                />

                <FilterProductSelect
                    id="highlight-product"
                    v-model="filters.productIds"
                    :label="t('cellLog.filters.product')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="products"
                />

                <FilterNumberField
                    id="highlight-stale-after-days"
                    v-model="filters.staleAfterDays"
                    :label="t('cellHighlight.staleAfterDays')"
                    :placeholder="t('cellHighlight.staleAfterDaysPlaceholder')"
                />
            </div>

            <div
                class="mt-6 flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
            >
                <button
                    type="button"
                    :class="filterClearButtonClass"
                    @click="clear"
                >
                    <X class="h-4 w-4 shrink-0" />
                    {{ t('cellLog.filters.clear') }}
                </button>
            </div>
        </FilterDialog>
    </div>
</template>
