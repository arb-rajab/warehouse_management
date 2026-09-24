<script setup lang="ts" generic="T extends { id: number }">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Filter,
    PackageSearch,
} from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';

type Column =
    | string
    | {
          label: string;
          sortKey?: string;
          filtered?: boolean;
          filterKey?: string;
          /**
           * Set to `false` when several columns share one `filterKey` (so
           * tapping any of their icons opens the same popover) and this
           * particular column isn't the one meant to advertise it — its
           * icon then only appears once `filtered` is actually true,
           * instead of sitting there year-round inviting a tap that does
           * nothing this column doesn't already offer via a sibling.
           * Defaults to `true`.
           */
          filterIconAlwaysVisible?: boolean;
      };

defineProps<{
    columns: Column[];
    rows: T[];
    emptyMessage: string;
    sort?: { by: string; direction: 'asc' | 'desc' };
}>();

const emit = defineEmits<{
    sort: [sortKey: string];
}>();

/**
 * Which column's filter popover is open, if any — a two-way model so the
 * parent page can gate its own "apply on change" watcher to only fire while
 * a popover (not the full filter dialog) is driving the edit.
 */
const openFilterKey = defineModel<string | null>('openFilterKey', {
    default: null,
});

function columnLabel(column: Column): string {
    return typeof column === 'string' ? column : column.label;
}

function sortKey(column: Column): string | null {
    return typeof column === 'string' ? null : (column.sortKey ?? null);
}

function filterKey(column: Column): string | null {
    return typeof column === 'string' ? null : (column.filterKey ?? null);
}

/**
 * Whether an active filter currently narrows or drives this column's
 * values — the parent page computes this per column from its own filter
 * state; DataTable only renders the resulting indicator (same split of
 * responsibility as the sort arrows above).
 */
function isFiltered(column: Column): boolean {
    return typeof column !== 'string' && (column.filtered ?? false);
}

function showsFilterIcon(column: Column): boolean {
    if (filterKey(column) === null) {
        return false;
    }

    const alwaysVisible =
        typeof column === 'string'
            ? true
            : (column.filterIconAlwaysVisible ?? true);

    return alwaysVisible || isFiltered(column);
}

const outerRef = ref<HTMLElement | null>(null);
const popoverRef = ref<HTMLElement | null>(null);
const popoverStyle = ref<{ top: string; left: string }>({
    top: '0px',
    left: '0px',
});

/**
 * Several columns can share one `filterKey` (see js-components.md), so this
 * maps a filterKey to every trigger button currently registered under it,
 * keyed by that column's index — a plain `Map<string, HTMLElement>` would
 * let the last-mounted sibling's button silently overwrite the others,
 * failing the outside-click containment check below for every other
 * sibling's icon.
 */
const triggerRefs = new Map<string, Map<number, HTMLElement>>();

function setTriggerRef(el: Element | null, key: string, index: number): void {
    const refs = triggerRefs.get(key) ?? new Map<number, HTMLElement>();

    if (el instanceof HTMLElement) {
        refs.set(index, el);
    } else {
        refs.delete(index);
    }

    if (refs.size > 0) {
        triggerRefs.set(key, refs);
    } else {
        triggerRefs.delete(key);
    }
}

/**
 * The popover renders as a sibling of the table's own `overflow-hidden`
 * wrapper (that wrapper exists only to clip the header background to the
 * rounded corners) instead of nested inside a per-column `position:
 * relative` anchor — nesting it there clipped the popover itself whenever
 * the table was shorter than the popover. Its position is computed here
 * from the clicked trigger's own rect instead.
 *
 * `getBoundingClientRect().left` is always the physical left edge regardless
 * of direction, so the offset below must land on the physical `left` CSS
 * property. It was previously assigned to the logical `insetInlineStart`,
 * which resolves to `right` under `dir="rtl"` — silently reinterpreting a
 * physical-left offset as a distance from the *right* edge and throwing the
 * popover far from its trigger (and, for a trigger near the table's visual
 * right side, off the physical left edge of the page entirely).
 */
function positionPopover(trigger: HTMLElement): void {
    const outer = outerRef.value;

    if (!outer) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const outerRect = outer.getBoundingClientRect();

    popoverStyle.value = {
        top: `${triggerRect.bottom - outerRect.top + 4}px`,
        left: `${triggerRect.left - outerRect.left}px`,
    };
}

function toggleFilterPopover(key: string, event: MouseEvent): void {
    if (openFilterKey.value === key) {
        openFilterKey.value = null;

        return;
    }

    openFilterKey.value = key;
    positionPopover(event.currentTarget as HTMLElement);
}

function onDocumentClick(event: MouseEvent): void {
    const key = openFilterKey.value;

    if (key === null) {
        return;
    }

    const target = event.target as Node;
    const withinTrigger = Array.from(triggerRefs.get(key)?.values() ?? []).some(
        (el) => el.contains(target),
    );
    const withinPopover = popoverRef.value?.contains(target) ?? false;

    if (!withinTrigger && !withinPopover) {
        openFilterKey.value = null;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        openFilterKey.value = null;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="outerRef" class="relative">
        <div
            class="overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-800"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-start text-sm">
                    <thead
                        class="bg-gray-50 text-gray-500 dark:bg-neutral-900 dark:text-neutral-400"
                    >
                        <tr>
                            <th
                                v-for="(column, index) in columns"
                                :key="index"
                                class="px-4 py-2 font-medium"
                            >
                                <div class="flex items-center gap-1">
                                    <button
                                        v-if="sortKey(column)"
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                        @click="emit('sort', sortKey(column)!)"
                                    >
                                        {{ columnLabel(column) }}
                                        <ArrowUp
                                            v-if="
                                                sort?.by === sortKey(column) &&
                                                sort.direction === 'asc'
                                            "
                                            class="h-3 w-3 shrink-0"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sort?.by === sortKey(column)
                                            "
                                            class="h-3 w-3 shrink-0"
                                        />
                                        <ArrowUpDown
                                            v-else
                                            class="h-3 w-3 shrink-0 text-gray-300 dark:text-neutral-600"
                                        />
                                    </button>
                                    <span v-else>{{
                                        columnLabel(column)
                                    }}</span>

                                    <button
                                        v-if="showsFilterIcon(column)"
                                        type="button"
                                        class="cursor-pointer rounded p-0.5"
                                        :class="
                                            isFiltered(column)
                                                ? 'text-blue-600 dark:text-blue-400'
                                                : 'text-gray-300 hover:text-gray-500 dark:text-neutral-600 dark:hover:text-neutral-400'
                                        "
                                        :title="t('common.filteredColumn')"
                                        :aria-expanded="
                                            openFilterKey === filterKey(column)
                                        "
                                        :ref="
                                            (el) =>
                                                setTriggerRef(
                                                    el as Element | null,
                                                    filterKey(column)!,
                                                    index,
                                                )
                                        "
                                        @click="
                                            toggleFilterPopover(
                                                filterKey(column)!,
                                                $event,
                                            )
                                        "
                                    >
                                        <Filter class="h-3 w-3 shrink-0" />
                                    </button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-neutral-800"
                    >
                        <tr v-for="row in rows" :key="row.id">
                            <slot name="row" :row="row" />
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td
                                :colspan="columns.length"
                                class="px-4 py-6 text-center text-gray-500 dark:text-neutral-400"
                            >
                                <div class="flex flex-col items-center gap-2">
                                    <PackageSearch
                                        class="h-6 w-6 text-gray-300 dark:text-neutral-700"
                                    />
                                    <span>{{ emptyMessage }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="openFilterKey !== null"
            ref="popoverRef"
            class="absolute z-20 w-64 rounded-md border border-gray-300 bg-white p-3 font-normal text-gray-700 shadow-lg dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"
            :style="popoverStyle"
        >
            <slot name="column-filter" :filter-key="openFilterKey" />
        </div>
    </div>
</template>
