<script setup lang="ts">
import { ChevronDown, LoaderCircle, Search } from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { search as searchWarehouses } from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import {
    useDismissibleListbox,
    useMultiSelectToggle,
    usePanelMaxWidth,
    usePanelViewportTracking,
} from '@/lib/useDismissibleListbox';
import { useSearchOptions } from '@/lib/useSearchOptions';
import type { WarehouseSummary } from '@/types/admin';

/**
 * The warehouse filter, shaped like FilterProductSelect: a multi-select whose
 * options are searched on demand (`admin/warehouses/search`, paged as the list
 * scrolls) rather than loaded in full, with the already-selected warehouses
 * hydrated from the page's `filterOptions.warehouses` (`selected`) and pinned
 * on top so they stay checkable after a new search replaces the results.
 */
const props = defineProps<{
    id: string;
    label: string;
    allLabel: string;
    selectedCountLabel: (count: number) => string;
    selected: WarehouseSummary[];
}>();

const model = defineModel<string[]>({ required: true });

const searchInputRef = ref<HTMLInputElement | null>(null);
const optionsListRef = ref<HTMLElement | null>(null);

/**
 * Every warehouse ever seen (from `selected` or a search page), so a checked
 * warehouse keeps its name on the closed trigger and in the pinned section
 * even after a new search replaces `results` or scrolls it out of view.
 */
const knownWarehouses = reactive(new Map<number, WarehouseSummary>());

function rememberWarehouses(warehouses: WarehouseSummary[]): void {
    for (const warehouse of warehouses) {
        knownWarehouses.set(warehouse.id, warehouse);
    }
}

const { query, results, loading, fetchFirstPageIfEmpty, onOptionsScroll } =
    useSearchOptions<WarehouseSummary>({
        endpoint: searchWarehouses,
        onResults: rememberWarehouses,
    });

watch(() => props.selected, rememberWarehouses, { immediate: true });

const { isChecked, toggleValue } = useMultiSelectToggle(model);

const pinnedWarehouses = computed(() =>
    Array.from(knownWarehouses.values()).filter((warehouse) =>
        isChecked(warehouse.id.toString()),
    ),
);
const visibleResults = computed(() => {
    const pinnedIds = new Set(
        pinnedWarehouses.value.map((warehouse) => warehouse.id),
    );

    return results.value.filter((warehouse) => !pinnedIds.has(warehouse.id));
});

const { open, containerRef, setOptionRef, onOptionKeydown } =
    useDismissibleListbox(
        () => pinnedWarehouses.value.length + visibleResults.value.length,
    );
const {
    panelMaxWidthPx,
    panelPosition,
    recompute: recomputePanelGeometry,
} = usePanelMaxWidth(containerRef);

function toggleOpen(): void {
    open.value = !open.value;

    if (open.value) {
        recomputePanelGeometry();
        fetchFirstPageIfEmpty();

        nextTick(() => searchInputRef.value?.focus());
    }
}

usePanelViewportTracking(containerRef, open, recomputePanelGeometry);

function deselectAll(): void {
    model.value = [];
}

function buttonLabel(): string {
    if (model.value.length === 0) {
        return props.allLabel;
    }

    if (model.value.length === 1) {
        return (
            knownWarehouses.get(Number(model.value[0]))?.name ?? props.allLabel
        );
    }

    return props.selectedCountLabel(model.value.length);
}
</script>

<template>
    <div ref="containerRef" class="relative">
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <button
            :id="id"
            type="button"
            class="flex w-full min-w-48 cursor-pointer items-center justify-between gap-2 rounded-md border border-line-strong px-3 py-2 text-start text-sm"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="toggleOpen"
        >
            <span class="min-w-0 flex-1 truncate">{{ buttonLabel() }}</span>
            <ChevronDown class="h-4 w-4 shrink-0 text-muted" />
        </button>
        <div
            v-if="open"
            class="fixed z-[60] w-max rounded-lg border border-line-strong bg-surface p-2 shadow-pop"
            :style="{
                top: `${panelPosition.top}px`,
                insetInlineStart: `${panelPosition.insetInlineStart}px`,
                minWidth: `${panelPosition.minWidth}px`,
                maxWidth: `${panelMaxWidthPx}px`,
            }"
        >
            <div class="relative mb-2">
                <Search
                    class="pointer-events-none absolute start-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                />
                <input
                    ref="searchInputRef"
                    v-model="query"
                    type="search"
                    :placeholder="t('warehouses.filters.searchPlaceholder')"
                    class="w-full rounded-md border border-line-strong bg-surface py-1.5 ps-8 pe-2 text-sm"
                />
            </div>

            <div
                ref="optionsListRef"
                role="listbox"
                class="max-h-64 overflow-y-auto"
                @scroll="onOptionsScroll(optionsListRef)"
            >
                <template v-if="pinnedWarehouses.length > 0">
                    <div class="flex items-center justify-between px-2 py-1">
                        <p class="text-xs font-medium text-muted">
                            {{ t('cellLog.filters.selected') }}
                        </p>
                        <button
                            type="button"
                            data-testid="deselect-all-warehouses"
                            class="cursor-pointer text-xs font-medium text-link hover:underline"
                            @click="deselectAll"
                        >
                            {{ t('cellLog.filters.deselectAll') }}
                        </button>
                    </div>
                    <div class="divide-y divide-line">
                        <label
                            v-for="(warehouse, index) in pinnedWarehouses"
                            :key="`pinned-${warehouse.id}`"
                            role="option"
                            aria-selected="true"
                            class="flex cursor-pointer items-start gap-2 px-2 py-1.5 text-sm hover:bg-subtle"
                        >
                            <input
                                :ref="(el) => setOptionRef(el, index)"
                                type="checkbox"
                                class="mt-0.5 shrink-0"
                                checked
                                @change="toggleValue(warehouse.id.toString())"
                                @keydown="onOptionKeydown($event, index)"
                            />
                            <span
                                dir="auto"
                                class="min-w-0 [overflow-wrap:anywhere]"
                                data-testid="warehouse-option-name"
                                >{{ warehouse.name }}</span
                            >
                        </label>
                    </div>
                    <hr class="my-1 border-line" />
                </template>

                <p
                    v-if="loading && visibleResults.length === 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-muted"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
                <p
                    v-else-if="!loading && visibleResults.length === 0"
                    class="px-2 py-1 text-sm text-muted"
                >
                    {{ t('warehouses.filters.noWarehousesFound') }}
                </p>

                <div
                    v-if="visibleResults.length > 0"
                    class="divide-y divide-line"
                >
                    <label
                        v-for="(warehouse, index) in visibleResults"
                        :key="warehouse.id"
                        role="option"
                        :aria-selected="isChecked(warehouse.id.toString())"
                        class="flex cursor-pointer items-start gap-2 px-2 py-1.5 text-sm hover:bg-subtle"
                    >
                        <input
                            :ref="
                                (el) =>
                                    setOptionRef(
                                        el,
                                        pinnedWarehouses.length + index,
                                    )
                            "
                            type="checkbox"
                            class="mt-0.5 shrink-0"
                            :checked="isChecked(warehouse.id.toString())"
                            @change="toggleValue(warehouse.id.toString())"
                            @keydown="
                                onOptionKeydown(
                                    $event,
                                    pinnedWarehouses.length + index,
                                )
                            "
                        />
                        <span
                            dir="auto"
                            class="min-w-0 [overflow-wrap:anywhere]"
                            data-testid="warehouse-option-name"
                            >{{ warehouse.name }}</span
                        >
                    </label>
                </div>

                <p
                    v-if="loading && visibleResults.length > 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-muted"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
            </div>
        </div>
    </div>
</template>
