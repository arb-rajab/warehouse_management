function pathOf(url: string): string {
    return url.split('?')[0];
}

/**
 * Whether the nav link to `href` is the active one for the page at
 * `currentUrl`, among the links whose urls are `siblingUrls`.
 *
 * A link matches on its exact url or as a path prefix, compared on paths
 * alone (stat cards and filters add query strings, and so do some links). A
 * url that is itself a prefix of a sibling's (`/admin` under `/admin/rows`)
 * only wins when no longer sibling matches too, so exactly one link lights up.
 */
export function isActiveNavUrl(
    currentUrl: string,
    href: string,
    siblingUrls: string[],
): boolean {
    const currentPath = pathOf(currentUrl);
    const hrefPath = pathOf(href);
    const matches = (candidate: string) =>
        currentPath === candidate || currentPath.startsWith(`${candidate}/`);

    if (!matches(hrefPath)) {
        return false;
    }

    return !siblingUrls
        .map(pathOf)
        .some((url) => url.length > hrefPath.length && matches(url));
}
