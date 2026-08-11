import { describe, expect, it } from 'vitest';
import { isSupportedLocale, t } from './i18n';

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
