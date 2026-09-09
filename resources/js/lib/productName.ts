import { i18n } from './i18n';

/**
 * The product label to render for the app's current locale: the store's
 * Arabic `ar_name` under `ar`, its base `name` otherwise.
 *
 * Every payload carrying a product name ships both raw columns and lets this
 * pick — the backend resolves nothing (see .ai/rules/shared-database.md). The
 * two are passed as plain strings rather than as a product object because the
 * key names differ by payload: a product carries `name`/`ar_name`, while a
 * pallet summary carries `product_name`/`product_ar_name`.
 *
 * A plain exported function reading `i18n.global.locale.value` — not a
 * composable, not `usePage()` — matching `lib/date.ts`. That keeps it callable
 * from plain `.ts` modules and keeps components that `mount()` in isolation
 * working without the i18n plugin installed (see `lib/i18n.ts`).
 *
 * `||` rather than `??` is load-bearing: upstream `products.ar_name` is
 * `varchar(191)` NOT NULL, so a product the store never translated carries an
 * empty string rather than null and still has to fall back to the base name.
 */
export function productName(name: string, arName: string): string {
    return i18n.global.locale.value === 'ar' ? arName || name : name;
}

/**
 * The store's *other* name for a product — the one `productName()` did not
 * pick — or null when there is nothing worth showing beside it.
 *
 * Only the product search dropdowns render this, under the primary label: the
 * search scope matches a term against `name` OR `ar_name` regardless of the
 * active locale (see .ai/rules/shared-database.md), so a result can match on a
 * name the list would otherwise never show, leaving the worker unable to tell
 * why the row came back. Every other render site shows the primary label
 * alone.
 *
 * Null rather than an empty string for the two cases where a second line would
 * be noise: a product the store never translated (`ar_name` is `''`, so the
 * primary already fell back to `name`), and one whose two names are identical.
 */
export function productAlternateName(
    name: string,
    arName: string,
): string | null {
    const primary = productName(name, arName);
    const alternate = primary === name ? arName : name;

    return alternate !== '' && alternate !== primary ? alternate : null;
}
