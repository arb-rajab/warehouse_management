import { afterEach, describe, expect, it } from 'vitest';
import { i18n } from './i18n';
import { productName } from './productName';

describe('productName', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('renders the store base name when the locale is English', () => {
        expect(productName('Widgets', 'ودجات')).toBe('Widgets');
    });

    it("renders the store's Arabic name when the locale is Arabic", () => {
        i18n.global.locale.value = 'ar';

        expect(productName('Widgets', 'ودجات')).toBe('ودجات');
    });

    it('falls back to the base name for a product the store never translated', () => {
        i18n.global.locale.value = 'ar';

        expect(productName('Widgets', '')).toBe('Widgets');
    });

    it('renders the base name for an untranslated product in English too', () => {
        expect(productName('Widgets', '')).toBe('Widgets');
    });

    it('follows a locale change rather than caching the first read', () => {
        expect(productName('Widgets', 'ودجات')).toBe('Widgets');

        i18n.global.locale.value = 'ar';

        expect(productName('Widgets', 'ودجات')).toBe('ودجات');
    });
});
