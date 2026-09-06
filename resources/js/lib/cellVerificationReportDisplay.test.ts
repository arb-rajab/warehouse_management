import { describe, expect, it } from 'vitest';
import type { CellVerificationSnapshot } from '@/types/admin';
import { snapshotProductLabel } from './cellVerificationReportDisplay';

function snapshot(
    overrides: Partial<CellVerificationSnapshot> = {},
): CellVerificationSnapshot {
    return {
        cell_state: 'full',
        product: { id: 1, name: 'Widgets', image_url: null, boxes_count: 10 },
        boxes_count: 10,
        expiration_date: null,
        ...overrides,
    };
}

describe('snapshotProductLabel', () => {
    it('returns the product name when a product is present', () => {
        expect(
            snapshotProductLabel(
                snapshot({
                    product: {
                        id: 1,
                        name: 'Widgets',
                        image_url: null,
                        boxes_count: 10,
                    },
                }),
            ),
        ).toBe('Widgets');
    });

    it('returns null when there is no product', () => {
        expect(snapshotProductLabel(snapshot({ product: null }))).toBeNull();
    });
});
