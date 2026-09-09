import { describe, expect, it } from 'vitest';
import type { Cell } from '@/types/admin';
import {
    countActiveCellHighlightFilters,
    emptyCellHighlightFilters,
    matchesCellHighlight,
} from './cellHighlight';
import type { CellHighlightFiltersValue } from './cellHighlight';

function cell(
    overrides: Partial<Cell> = {},
    pallet: Partial<Cell['pallet']> | null = null,
): Cell {
    return {
        id: 1,
        cell_number: 1,
        flat_number: 1,
        state: 'full',
        is_active: true,
        pallet: pallet
            ? {
                  id: 1,
                  product_id: 1,
                  product_name: 'Widgets',
                  product_ar_name: 'ودجات',
                  product_image_url: null,
                  expiration_date: '2026-09-01',
                  added_at: '2026-08-01T00:00:00Z',
                  is_stale: null,
                  remaining_boxes: 10,
                  ...pallet,
              }
            : null,
        ...overrides,
    };
}

const today = '2026-08-13';

describe('countActiveCellHighlightFilters', () => {
    it('counts zero when nothing is set', () => {
        expect(
            countActiveCellHighlightFilters(emptyCellHighlightFilters()),
        ).toBe(0);
    });

    it('counts each independently-set field', () => {
        const filters: CellHighlightFiltersValue = {
            state: ['full'],
            expired: true,
            expiresWithinDays: '7',
            productIds: ['1'],
            staleAfterDays: '5',
            inactive: true,
        };

        expect(countActiveCellHighlightFilters(filters)).toBe(6);
    });
});

describe('matchesCellHighlight', () => {
    it('matches nothing when no filter is active', () => {
        expect(
            matchesCellHighlight(cell(), emptyCellHighlightFilters(), today),
        ).toBe(false);
    });

    it('matches by state', () => {
        const filters: CellHighlightFiltersValue = {
            ...emptyCellHighlightFilters(),
            state: ['opened'],
        };

        expect(
            matchesCellHighlight(cell({ state: 'full' }), filters, today),
        ).toBe(false);
        expect(
            matchesCellHighlight(cell({ state: 'opened' }), filters, today),
        ).toBe(true);
    });

    it('matches any of several selected states', () => {
        const filters: CellHighlightFiltersValue = {
            ...emptyCellHighlightFilters(),
            state: ['opened', 'full'],
        };

        expect(
            matchesCellHighlight(cell({ state: 'full' }), filters, today),
        ).toBe(true);
        expect(
            matchesCellHighlight(cell({ state: 'opened' }), filters, today),
        ).toBe(true);
        expect(
            matchesCellHighlight(cell({ state: 'empty' }), filters, today),
        ).toBe(false);
    });

    it('matches by expires-within-days, excluding already-expired pallets', () => {
        const filters = {
            ...emptyCellHighlightFilters(),
            expiresWithinDays: '5',
        };

        expect(
            matchesCellHighlight(
                cell({}, { expiration_date: '2026-08-01' }),
                filters,
                today,
            ),
        ).toBe(false);
        expect(
            matchesCellHighlight(
                cell({}, { expiration_date: '2026-08-17' }),
                filters,
                today,
            ),
        ).toBe(true);
        expect(
            matchesCellHighlight(
                cell({}, { expiration_date: '2026-09-01' }),
                filters,
                today,
            ),
        ).toBe(false);
    });

    it('matches by expired', () => {
        const filters = { ...emptyCellHighlightFilters(), expired: true };

        expect(
            matchesCellHighlight(
                cell({}, { expiration_date: '2026-08-01' }),
                filters,
                today,
            ),
        ).toBe(true);
        expect(
            matchesCellHighlight(
                cell({}, { expiration_date: '2026-08-13' }),
                filters,
                today,
            ),
        ).toBe(false);
        expect(
            matchesCellHighlight(
                cell({}, { expiration_date: '2026-09-01' }),
                filters,
                today,
            ),
        ).toBe(false);
    });

    it('matches by product id, not name', () => {
        const filters = { ...emptyCellHighlightFilters(), productIds: ['2'] };

        expect(
            matchesCellHighlight(cell({}, { product_id: 1 }), filters, today),
        ).toBe(false);
        expect(
            matchesCellHighlight(cell({}, { product_id: 2 }), filters, today),
        ).toBe(true);
    });

    it('does not match a product-id filter against a cell with no pallet', () => {
        const filters = { ...emptyCellHighlightFilters(), productIds: ['1'] };

        expect(matchesCellHighlight(cell(), filters, today)).toBe(false);
    });

    it('matches any of several selected product ids', () => {
        const filters = {
            ...emptyCellHighlightFilters(),
            productIds: ['1', '2'],
        };

        expect(
            matchesCellHighlight(cell({}, { product_id: 2 }), filters, today),
        ).toBe(true);
    });

    it('matches by stale-after-days', () => {
        const filters = { ...emptyCellHighlightFilters(), staleAfterDays: '5' };

        expect(
            matchesCellHighlight(
                cell({}, { added_at: '2026-08-01T00:00:00Z' }),
                filters,
                today,
            ),
        ).toBe(true);
        expect(
            matchesCellHighlight(
                cell({}, { added_at: '2026-08-12T00:00:00Z' }),
                filters,
                today,
            ),
        ).toBe(false);
    });

    it('matches by inactive, regardless of occupancy state', () => {
        const filters = { ...emptyCellHighlightFilters(), inactive: true };

        expect(
            matchesCellHighlight(cell({ is_active: true }), filters, today),
        ).toBe(false);
        expect(
            matchesCellHighlight(cell({ is_active: false }), filters, today),
        ).toBe(true);
    });

    it('requires every active filter to match at once', () => {
        const filters: CellHighlightFiltersValue = {
            state: ['full'],
            expired: false,
            expiresWithinDays: '',
            productIds: ['1'],
            staleAfterDays: '',
            inactive: false,
        };

        expect(
            matchesCellHighlight(
                cell({ state: 'full' }, { product_id: 2 }),
                filters,
                today,
            ),
        ).toBe(false);
        expect(
            matchesCellHighlight(
                cell({ state: 'full' }, { product_id: 1 }),
                filters,
                today,
            ),
        ).toBe(true);
    });

    it('never matches a null cell', () => {
        const filters: CellHighlightFiltersValue = {
            ...emptyCellHighlightFilters(),
            state: ['full'],
        };

        expect(matchesCellHighlight(null, filters, today)).toBe(false);
    });
});
