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

/** Keep translations and document metadata aligned on hard and Inertia visits. */
export function syncPageLocale(props: {
    locale?: unknown;
    fallbackLocale?: unknown;
    store?: { rtl?: boolean };
}): void {
    i18n.global.locale.value = isSupportedLocale(props.locale)
        ? props.locale
        : 'en';
    i18n.global.fallbackLocale.value = isSupportedLocale(props.fallbackLocale)
        ? props.fallbackLocale
        : 'en';

    if (typeof document !== 'undefined' && typeof props.locale === 'string') {
        document.documentElement.lang = props.locale.replaceAll('_', '-');
        document.documentElement.dir =
            (props.store?.rtl ?? props.locale === 'ar') ? 'rtl' : 'ltr';
    }
}
