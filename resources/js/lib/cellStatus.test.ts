import { describe, expect, it } from 'vitest';
import type { Cell } from '@/types/admin';
import { isCellExpired, isCellExpiringWithin, isCellStale } from './cellStatus';

function cell(pallet: Partial<Cell['pallet']> | null): Cell {
    return {
        id: 1,
        cell_number: 1,
        flat_number: 1,
        state: pallet ? 'full' : 'empty',
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
    };
}

describe('isCellExpiringWithin', () => {
    it('is false when withinDays is null, regardless of expiration date', () => {
        const result = isCellExpiringWithin(
            cell({ expiration_date: '2026-08-14' }),
            '2026-08-13',
            null,
        );

        expect(result).toBe(false);
    });

    it('is false for a cell with no pallet', () => {
        expect(isCellExpiringWithin(cell(null), '2026-08-13', 5)).toBe(false);
    });

    it('is false when the pallet already expired', () => {
        const result = isCellExpiringWithin(
            cell({ expiration_date: '2026-08-01' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(false);
    });

    it('is true when the pallet expires today', () => {
        const result = isCellExpiringWithin(
            cell({ expiration_date: '2026-08-13' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(true);
    });

    it('is true once the expiration date falls within the given day count', () => {
        const result = isCellExpiringWithin(
            cell({ expiration_date: '2026-08-16' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(true);
    });

    it('is false when the expiration date is beyond the given day count', () => {
        const result = isCellExpiringWithin(
            cell({ expiration_date: '2026-08-25' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(false);
    });

    it('is true exactly at the day-count boundary', () => {
        const result = isCellExpiringWithin(
            cell({ expiration_date: '2026-08-18' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(true);
    });
});

describe('isCellExpired', () => {
    it('is false for a cell with no pallet', () => {
        expect(isCellExpired(cell(null), '2026-08-13')).toBe(false);
    });

    it('is true when the expiration date is before today', () => {
        const result = isCellExpired(
            cell({ expiration_date: '2026-08-01' }),
            '2026-08-13',
        );

        expect(result).toBe(true);
    });

    it('is false when the pallet expires today', () => {
        const result = isCellExpired(
            cell({ expiration_date: '2026-08-13' }),
            '2026-08-13',
        );

        expect(result).toBe(false);
    });

    it('is false when the expiration date is in the future', () => {
        const result = isCellExpired(
            cell({ expiration_date: '2026-08-20' }),
            '2026-08-13',
        );

        expect(result).toBe(false);
    });
});

describe('isCellStale', () => {
    it('is false when staleAfterDays is null, regardless of age', () => {
        const result = isCellStale(
            cell({ added_at: '2026-01-01T00:00:00Z' }),
            '2026-08-13',
            null,
        );

        expect(result).toBe(false);
    });

    it('is false for a cell with no pallet', () => {
        expect(isCellStale(cell(null), '2026-08-13', 5)).toBe(false);
    });

    it('is false when the pallet has no added_at to age it from', () => {
        const result = isCellStale(cell({ added_at: null }), '2026-08-13', 5);

        expect(result).toBe(false);
    });

    it('is true once the pallet is older than the given day count', () => {
        const result = isCellStale(
            cell({ added_at: '2026-08-01T00:00:00Z' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(true);
    });

    it('is false when the pallet is younger than the given day count', () => {
        const result = isCellStale(
            cell({ added_at: '2026-08-10T00:00:00Z' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(false);
    });

    it('is true exactly at the day-count boundary', () => {
        const result = isCellStale(
            cell({ added_at: '2026-08-08T00:00:00Z' }),
            '2026-08-13',
            5,
        );

        expect(result).toBe(true);
    });
});
