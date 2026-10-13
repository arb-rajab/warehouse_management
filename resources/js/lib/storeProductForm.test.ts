import { describe, expect, it } from 'vitest';
import { discountRangePattern, productAmountMax } from '@/lib/storeProductForm';

describe('storeProductForm', () => {
    it('bounds amounts by the DECIMAL(20,2) columns', () => {
        expect(productAmountMax).toBe('999999999999999999.99');
    });

    it('matches only the legacy discount range format', () => {
        const pattern = new RegExp(`^(?:${discountRangePattern})$`, 'v');

        expect(pattern.test('01-10-2026 00:00:00 to 31-10-2026 23:59:59')).toBe(
            true,
        );
        expect(pattern.test('2026-10-01 to 2026-10-31')).toBe(false);
    });
});
