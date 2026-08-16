import { isCellExpiringWithin, isCellStale } from '@/lib/cellStatus';
import type { Cell } from '@/types/admin';

/**
 * The highlight-filter form state shared by Rows/Show and the Cells map —
 * numeric/id fields are kept as strings to match native form-control values
 * (`''` means "not set").
 */
export interface CellHighlightFiltersValue {
    state: Cell['state'][];
    expiresWithinDays: string;
    productIds: string[];
    staleAfterDays: string;
}

export function emptyCellHighlightFilters(): CellHighlightFiltersValue {
    return {
        state: [],
        expiresWithinDays: '',
        productIds: [],
        staleAfterDays: '',
    };
}

export function countActiveCellHighlightFilters(
    filters: CellHighlightFiltersValue,
): number {
    return [
        filters.state.length > 0,
        filters.expiresWithinDays !== '',
        filters.productIds.length > 0,
        filters.staleAfterDays !== '',
    ].filter(Boolean).length;
}

/**
 * Whether a cell matches every active highlight filter. Returns false when no
 * filter is active at all, so callers don't have to special-case "nothing
 * selected" before deciding whether to draw a highlight ring.
 */
export function matchesCellHighlight(
    cell: Cell | null,
    filters: CellHighlightFiltersValue,
    today: string,
): boolean {
    if (!cell || countActiveCellHighlightFilters(filters) === 0) {
        return false;
    }

    if (filters.state.length > 0 && !filters.state.includes(cell.state)) {
        return false;
    }

    if (
        filters.expiresWithinDays !== '' &&
        !isCellExpiringWithin(cell, today, Number(filters.expiresWithinDays))
    ) {
        return false;
    }

    if (filters.productIds.length > 0) {
        const productId = cell.pallet?.product_id.toString();

        if (!productId || !filters.productIds.includes(productId)) {
            return false;
        }
    }

    if (
        filters.staleAfterDays !== '' &&
        !isCellStale(cell, today, Number(filters.staleAfterDays))
    ) {
        return false;
    }

    return true;
}
