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
        pallet: pallet
            ? {
                  id: 1,
                  product_id: 1,
                  product_name: 'Widgets',
                  product_image_url: null,
                  expiration_date: '2026-09-01',
                  added_at: '2026-08-01T00:00:00Z',
                  is_stale: null,
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
            expiresWithinDays: '7',
            productIds: ['1'],
            staleAfterDays: '5',
        };

        expect(countActiveCellHighlightFilters(filters)).toBe(4);
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

    it('matches by expires-within-days', () => {
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
        ).toBe(true);
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

    it('matches by product id, not name', () => {
        const filters = { ...emptyCellHighlightFilters(), productIds: ['2'] };

        expect(
            matchesCellHighlight(cell({}, { product_id: 1 }), filters, today),
        ).toBe(false);
        expect(
            matchesCellHighlight(cell({}, { product_id: 2 }), filters, today),
        ).toBe(true);
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

    it('requires every active filter to match at once', () => {
        const filters: CellHighlightFiltersValue = {
            state: ['full'],
            expiresWithinDays: '',
            productIds: ['1'],
            staleAfterDays: '',
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
