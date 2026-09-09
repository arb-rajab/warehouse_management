import { afterEach, describe, expect, it } from 'vitest';
import type { CellVerificationSnapshot } from '@/types/admin';
import { snapshotProductLabel } from './cellVerificationReportDisplay';
import { i18n } from './i18n';

function snapshot(
    overrides: Partial<CellVerificationSnapshot> = {},
): CellVerificationSnapshot {
    return {
        cell_state: 'full',
        product: {
            id: 1,
            name: 'Widgets',
            ar_name: 'ودجات',
            image_url: null,
            boxes_count: 10,
        },
        boxes_count: 10,
        expiration_date: null,
        ...overrides,
    };
}

describe('snapshotProductLabel', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('returns the product name when a product is present', () => {
        expect(
            snapshotProductLabel(
                snapshot({
                    product: {
                        id: 1,
                        name: 'Widgets',
                        ar_name: 'ودجات',
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

    it("returns the product's Arabic name when the locale is Arabic", () => {
        i18n.global.locale.value = 'ar';

        expect(snapshotProductLabel(snapshot())).toBe('ودجات');
    });

    it('falls back to the base name for a product the store never translated', () => {
        i18n.global.locale.value = 'ar';

        expect(
            snapshotProductLabel(
                snapshot({
                    product: {
                        id: 1,
                        name: 'Widgets',
                        ar_name: '',
                        image_url: null,
                        boxes_count: 10,
                    },
                }),
            ),
        ).toBe('Widgets');
    });
});
