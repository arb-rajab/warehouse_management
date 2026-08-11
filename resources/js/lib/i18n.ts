import { createI18n } from 'vue-i18n';
import ar from '@/locales/ar.json';
import en from '@/locales/en.json';

export type SupportedLocale = 'en' | 'ar';

export function isSupportedLocale(value: unknown): value is SupportedLocale {
    return value === 'en' || value === 'ar';
}

export const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en, ar },
});

/**
 * A plain function (not `$t`) so components can translate without the i18n
 * plugin being installed on their particular app instance — this keeps unit
 * tests that `mount()` a component in isolation working unchanged.
 */
export function t(key: string, params?: Record<string, unknown>): string {
    return i18n.global.t(key, params ?? {});
}
