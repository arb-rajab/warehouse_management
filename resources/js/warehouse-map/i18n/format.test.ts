import { describe, expect, it } from 'vitest';
import { formatDate, palletName } from './format';

describe('formatDate', () => {
    it("formats a date in the viewer's language, with Western digits in Arabic", () => {
        expect(formatDate('2026-12-31', 'en')).toBe('31 Dec 2026');
        expect(formatDate('2026-12-31', 'ar')).toMatch(/2026/);
        expect(formatDate('2026-12-31', 'ar')).toMatch(/31/);
    });

    it('is empty without a date and echoes one it cannot read', () => {
        expect(formatDate(null, 'en')).toBe('');
        expect(formatDate('soon', 'en')).toBe('soon');
    });
});

describe('palletName', () => {
    const pallet = { productName: 'Rice', productArName: 'أرز' };

    it("prefers the name in the viewer's language and falls back to the other", () => {
        expect(palletName(pallet, 'en')).toBe('Rice');
        expect(palletName(pallet, 'ar')).toBe('أرز');
        expect(
            palletName({ productName: 'Rice', productArName: '' }, 'ar'),
        ).toBe('Rice');
        expect(
            palletName({ productName: '', productArName: 'أرز' }, 'en'),
        ).toBe('أرز');
        expect(palletName(null, 'en')).toBe('');
    });
});
