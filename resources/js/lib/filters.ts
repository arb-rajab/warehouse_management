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
 * The filter-field label shared by every FilterDateField/FilterMultiSelect/
 * FilterNumberField/FilterSelect/FormField.
 */
export const fieldLabelClass =
    'mb-1 block text-sm text-gray-700 dark:text-neutral-300';

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
