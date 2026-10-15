import type { Locale } from '../domain/types';

/** Arabic uses Western digits here, like the rest of the panel. */
const TAGS: Record<Locale, string> = { en: 'en-GB', ar: 'ar-u-nu-latn' };

export function formatDate(isoDate: string | null, locale: Locale): string {
    if (!isoDate) {
        return '';
    }

    const date = new Date(`${isoDate}T12:00:00`);

    if (Number.isNaN(date.getTime())) {
        return isoDate;
    }

    return date.toLocaleDateString(TAGS[locale], {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

interface Named {
    productName: string;
    productArName: string;
}

/** The product name in the viewer's language, falling back to the other one. */
export function palletName(pallet: Named | null, locale: Locale): string {
    if (!pallet) {
        return '';
    }

    return locale === 'ar'
        ? pallet.productArName || pallet.productName
        : pallet.productName || pallet.productArName;
}
