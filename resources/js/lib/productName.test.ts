import { afterEach, describe, expect, it } from 'vitest';
import { i18n } from './i18n';
import { productAlternateName, productName } from './productName';

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

describe('productAlternateName', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it("returns the store's Arabic name beside an English primary label", () => {
        expect(productAlternateName('Widgets', 'ودجات')).toBe('ودجات');
    });

    it("returns the store's base name beside an Arabic primary label", () => {
        i18n.global.locale.value = 'ar';

        expect(productAlternateName('Widgets', 'ودجات')).toBe('Widgets');
    });

    it('returns null for a product the store never translated, in either locale', () => {
        expect(productAlternateName('Widgets', '')).toBeNull();

        i18n.global.locale.value = 'ar';

        // The primary label already fell back to the base name, so a second
        // line would repeat it.
        expect(productAlternateName('Widgets', '')).toBeNull();
    });

    it('returns null when the store stored the same string in both columns', () => {
        expect(productAlternateName('Widgets', 'Widgets')).toBeNull();

        i18n.global.locale.value = 'ar';

        expect(productAlternateName('Widgets', 'Widgets')).toBeNull();
    });

    it('never returns the label productName picked', () => {
        i18n.global.locale.value = 'ar';

        expect(productAlternateName('Widgets', 'ودجات')).not.toBe(
            productName('Widgets', 'ودجات'),
        );
    });
});
