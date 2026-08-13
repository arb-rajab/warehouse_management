/**
 * The heading style shared by every section inside a filter dialog (see
 * Cells/Index.vue and CellStatusLogs/Index.vue).
 */
export const filterSectionHeadingClass =
    'mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-neutral-400';

/**
 * The 1..maxColumnNumber options for a "column" filter dropdown.
 */
export function columnNumberOptions(maxColumnNumber: number): number[] {
    return Array.from({ length: maxColumnNumber }, (_, i) => i + 1);
}

/**
 * Flips sort direction when re-clicking the active column, defaults to
 * ascending on a newly-clicked one, then re-applies the filters.
 */
export function applySortToggle(
    filters: { sort_by: string; sort_direction: string },
    key: string,
    applyFilters: () => void,
): void {
    filters.sort_direction =
        filters.sort_by === key && filters.sort_direction === 'asc'
            ? 'desc'
            : 'asc';
    filters.sort_by = key;
    applyFilters();
}
