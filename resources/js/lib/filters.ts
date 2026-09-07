import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { t } from '@/lib/i18n';

/**
 * The heading style shared by every section inside a filter dialog (see
 * Cells/Index.vue and CellStatusLogs/Index.vue).
 */
export const filterSectionHeadingClass =
    'mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-neutral-400';

/**
 * The small rounded-pill count badge shared by every filter-dialog trigger's
 * active-filter count (CellHighlightFilters.vue, CellStatusLogs/Index.vue)
 * and the cell map's per-flat highlight-match count (Cells/Index.vue). Blue
 * rather than the page's usual gray-900/white pairing so it stays visible on
 * the flat tab's own dark selected-state background, matching the blue
 * highlight ring cells get.
 */
export const countBadgeClass =
    'inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-xs font-medium text-white dark:bg-blue-500';

/**
 * The "open filter dialog" trigger button shared by CellHighlightFilters.vue
 * and CellStatusLogs/Index.vue.
 */
export const filterTriggerButtonClass =
    'inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800 cursor-pointer';

/**
 * The filter-dialog "Clear" button shared by CellHighlightFilters.vue and
 * CellStatusLogs/Index.vue.
 */
export const filterClearButtonClass =
    'inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800 cursor-pointer';

/**
 * The cell map's toolbar buttons (zoom out/in, rotate left/right, reset
 * view) shared by Cells/Index.vue.
 */
export const mapToolbarButtonClass =
    'cursor-pointer rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800';

/**
 * The highlight-match previous/next nav buttons shared by Cells/Index.vue —
 * same look as mapToolbarButtonClass but at the smaller p-1.5 size used
 * alongside the match-count label.
 */
export const matchNavButtonClass =
    'cursor-pointer rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800';

/**
 * The "this toggle is the active one" variant applied on top of a toggle
 * button (flat tabs, 2D/3D view mode, orbit camera mode, touch sprint) in
 * Cells/Index.vue and CellMap3D.vue.
 */
export const selectedToggleClass =
    'bg-gray-900 text-white dark:bg-white dark:text-gray-900';

/**
 * The filter-dialog "Apply" submit button shared by CellStatusLogs/Index.vue
 * and Dashboard/Index.vue's custom-expiring-days dialog.
 */
export const filterApplyButtonClass =
    'inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200';

/**
 * The filter-field label shared by every FilterDateField/FilterMultiSelect/
 * FilterNumberField/FilterSelect/FormField.
 */
export const fieldLabelClass =
    'mb-1 block text-sm text-gray-700 dark:text-neutral-300';

/**
 * The plain bordered text/number/date/select/textarea input shared by every
 * field in PalletActionsDialog.vue and ToggleCellActiveDialog.vue's note
 * field — extracted once it was repeated 7 times across the two files.
 */
export const plainFieldInputClass =
    'w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

/**
 * The `selectedCountLabel` FilterMultiSelect expects, shared by every
 * multi-select filter (product/action/user/state) across the admin pages.
 */
export function selectedCountLabel(count: number): string {
    return t('cellLog.filters.selectedCount', { count });
}

/**
 * The 1..maxColumnNumber options for a "column" filter dropdown.
 */
export function columnNumberOptions(maxColumnNumber: number): number[] {
    return Array.from({ length: maxColumnNumber }, (_, i) => i + 1);
}

/**
 * Counts how many of the given flags are true — shared by every
 * active-filter-count computed (CellStatusLogs/Index.vue's
 * `activeFilterCount`, cellHighlight.ts's `countActiveCellHighlightFilters`).
 */
export function countActive(flags: boolean[]): number {
    return flags.filter(Boolean).length;
}

/**
 * Toggles a reactive filters object's `sort_by`/`sort_direction` for the
 * clicked column key — ascending on a new column, otherwise flipping the
 * current direction. Shared by every sortable admin listing
 * (CellStatusLogs/Index.vue, Products/Index.vue); the caller re-applies the
 * filters afterward (e.g. via `router.get`).
 */
export function toggleSort(
    filters: { sort_by: string; sort_direction: string },
    key: string,
): void {
    filters.sort_direction =
        filters.sort_by === key && filters.sort_direction === 'asc'
            ? 'desc'
            : 'asc';
    filters.sort_by = key;
}

/**
 * Delays calling `fn` until `delayMs` have passed without another call —
 * shared by FilterProductSelect.vue's search-as-you-type and the
 * column-filter popovers' apply-on-change watchers (CellStatusLogs/Index.vue,
 * Products/Index.vue), so free-typed text/number/date edits don't fire a
 * request per keystroke.
 */
/**
 * Enforces mutual exclusion between a date-range filter pair and a
 * day-count filter pair — filling one disables the other. Shared by
 * CellStatusLogs/Index.vue (date/created_within_days,
 * expiration_date/expires_within_days) and Products/Index.vue
 * (date/created_within_days).
 */
export function exclusivePair(
    rangeFilled: () => boolean,
    daysFilled: () => boolean,
): { rangeDisabled: ComputedRef<boolean>; daysDisabled: ComputedRef<boolean> } {
    return {
        rangeDisabled: computed(daysFilled),
        daysDisabled: computed(rangeFilled),
    };
}

export function debounce<Args extends unknown[]>(
    fn: (...args: Args) => void,
    delayMs: number,
): (...args: Args) => void {
    let timer: ReturnType<typeof setTimeout> | null = null;

    return (...args: Args) => {
        if (timer !== null) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => fn(...args), delayMs);
    };
}

/**
 * The column-filter-popover auto-apply wiring shared by CellStatusLogs/Index.vue
 * and Products/Index.vue: which column's popover is open (bound two-way to
 * DataTable), and a debounced re-apply triggered only while a popover — not
 * the full filter dialog — is open, so editing the same `filters` fields
 * from the main dialog doesn't also trigger a premature navigation before
 * its own Apply is clicked. `filtersOpen` stays owned by the caller since it
 * also gates the main dialog's own open/close state.
 */
export function useColumnFilterPopover(
    filters: object,
    filtersOpen: Ref<boolean>,
    applyFilters: () => void,
): { openFilterKey: Ref<string | null> } {
    const openFilterKey = ref<string | null>(null);
    const debouncedApplyFilters = debounce(applyFilters, 400);

    watch(
        filters,
        () => {
            if (openFilterKey.value !== null && !filtersOpen.value) {
                debouncedApplyFilters();
            }
        },
        { deep: true },
    );

    return { openFilterKey };
}
