import { afterEach, describe, expect, it } from 'vitest';
import { i18n, isSupportedLocale, syncPageLocale, t } from './i18n';

describe('isSupportedLocale', () => {
    it('accepts the supported locale codes', () => {
        expect(isSupportedLocale('en')).toBe(true);
        expect(isSupportedLocale('ar')).toBe(true);
    });

    it('rejects unsupported or non-string values', () => {
        expect(isSupportedLocale('fr')).toBe(false);
        expect(isSupportedLocale('')).toBe(false);
        expect(isSupportedLocale(null)).toBe(false);
        expect(isSupportedLocale(undefined)).toBe(false);
        expect(isSupportedLocale(1)).toBe(false);
    });
});

describe('t', () => {
    it('translates a key without needing the i18n plugin installed', () => {
        expect(t('language.label')).toBe('Language');
    });

    it('interpolates params into the translated message', () => {
        expect(t('rows.show.title', { letter: 'A' })).toBe('Row A');
    });
});

describe('syncPageLocale', () => {
    afterEach(() => syncPageLocale({ locale: 'en' }));

    it('updates translations and metadata when navigating to Arabic', () => {
        syncPageLocale({
            locale: 'ar',
            fallbackLocale: 'en',
            store: { rtl: true },
        });
        expect(i18n.global.locale.value).toBe('ar');
        expect(t('language.label')).not.toBe('Language');
        expect(document.documentElement.lang).toBe('ar');
        expect(document.documentElement.dir).toBe('rtl');
        syncPageLocale({ locale: 'en', store: { rtl: false } });
        expect(t('language.label')).toBe('Language');
        expect(document.documentElement.dir).toBe('ltr');
    });

    it('resets unsupported UI languages to English and honors the configured fallback', () => {
        syncPageLocale({ locale: 'ar' });
        syncPageLocale({
            locale: 'fa',
            fallbackLocale: 'ar',
            store: { rtl: true },
        });
        expect(i18n.global.locale.value).toBe('en');
        expect(i18n.global.fallbackLocale.value).toBe('ar');
        expect(document.documentElement.lang).toBe('fa');
        expect(document.documentElement.dir).toBe('rtl');
    });
});
