import type { StoreMenuItem, StoreMenuLink } from '@/lib/storeAdminMenu';

/**
 * The one place that decides which Store admin menu link is active
 * (.ai/rules/store-admin-menu-highlighting.md). StoreAdminLayout, its group
 * headers and every test go through `findActiveStoreMenuLink`; nothing else
 * compares URLs against the menu.
 */

/** A Wayfinder route function (or any object carrying its `definition`). */
export interface StoreRouteLike {
    definition: { url: string };
}

/** A path, a Wayfinder URL, or a Wayfinder route function. */
export type StoreRouteRef = string | StoreRouteLike;

/**
 * A page that is not a menu entry, mapped to the entry it belongs to.
 * `page` is a prefix: every URL equal to it or below it (on a segment
 * boundary) belongs to `parent`. A `null` parent says the page is reached
 * from outside the menu (the profile, the notifications bell), so nothing is
 * highlighted for it. Route parameters (`{customer}`) and everything after the
 * first one are dropped from a Wayfinder route to get that prefix.
 */
export interface StoreMenuParent {
    page: StoreRouteRef;
    parent: StoreRouteRef | null;
}

/** The path of `url`: no origin, query string, fragment or trailing slash. */
export function normalizeStorePath(url: string): string {
    const withoutOrigin = url.replace(/^[a-z][a-z\d+.-]*:\/\/[^/]+/i, '');
    const path = withoutOrigin.split(/[?#]/)[0].replace(/\/+$/, '');

    return path === '' ? '/' : path;
}

/** The static path of a route reference, cut before its first `{param}`. */
export function storeRoutePrefix(ref: StoreRouteRef): string {
    const raw = typeof ref === 'string' ? ref : ref.definition.url;

    return normalizeStorePath(raw.split('{')[0]);
}

/** Whether `path` is `base` or lies below it, on a segment boundary. */
export function pathIsWithin(path: string, base: string): boolean {
    return base === '/' || path === base || path.startsWith(`${base}/`);
}

/**
 * The paths a link is the home of. A link ends `/index` where the route
 * names its list page that way (`/trips/index`); its detail pages then sit
 * beside it (`/trips/{trip}/edit`), so the directory counts too.
 */
function linkBases(link: StoreMenuLink): string[] {
    const path = normalizeStorePath(link.href.url);

    if (link.exact) {
        return [path];
    }

    return path.endsWith('/index')
        ? [path, path.slice(0, -'/index'.length)]
        : [path];
}

interface Candidate {
    base: string;
    link: StoreMenuLink | null;
    /** Matches `base` itself only, never the pages below it. */
    exact: boolean;
}

/**
 * Flattens the menu, nested groups included, in order.
 * Kept here so the helper has no runtime dependency on the menu module.
 */
function flatten(items: StoreMenuItem[]): StoreMenuLink[] {
    return items.flatMap((item) =>
        'children' in item ? flatten(item.children) : [item],
    );
}

/**
 * The menu link active for `currentUrl`, or `null` when none is.
 *
 * - A link matches when the URL's path equals its path or continues it after a
 *   `/` (`/products` matches `/products/5/edit`, never `/products-categories`).
 *   The query string, fragment and a trailing slash are ignored.
 * - A page named in `parents` counts as its parent link's.
 * - The longest matching path wins, so exactly one link is active and a
 *   specific entry beats a broad one. A link marked `exact` (the dashboard,
 *   whose path prefixes every page) matches its own path only.
 * - A page matching nothing, or mapped to a `null` parent, activates nothing.
 *
 * Pass the whole menu, never a permission- or search-filtered copy, so the
 * answer does not change with who is looking or what they typed.
 */
export function findActiveStoreMenuLink(
    currentUrl: string,
    menu: StoreMenuItem[],
    parents: StoreMenuParent[] = [],
): StoreMenuLink | null {
    const path = normalizeStorePath(currentUrl);
    const links = flatten(menu);
    const candidates: Candidate[] = links.flatMap((link) =>
        linkBases(link).map((base) => ({
            base,
            link,
            exact: link.exact === true,
        })),
    );

    for (const { page, parent } of parents) {
        const target =
            parent === null
                ? null
                : (links.find(
                      (link) =>
                          normalizeStorePath(link.href.url) ===
                          storeRoutePrefix(parent),
                  ) ?? null);

        candidates.push({
            base: storeRoutePrefix(page),
            link: target,
            exact: false,
        });
    }

    let best: Candidate | null = null;

    for (const candidate of candidates) {
        const matches = candidate.exact
            ? path === candidate.base
            : pathIsWithin(path, candidate.base);

        if (
            matches &&
            (best === null || candidate.base.length > best.base.length)
        ) {
            best = candidate;
        }
    }

    return best?.link ?? null;
}

/** Whether `item` is, or (for a group) holds, the active link. */
export function storeMenuItemContains(
    item: StoreMenuItem,
    active: StoreMenuLink | null,
): boolean {
    if (active === null) {
        return false;
    }

    return flatten([item]).includes(active);
}
