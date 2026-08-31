/**
 * The shared "pill" sizing/layout for a table-row action link, and the blue
 * "primary" variant's color classes — used by TableActionLink.vue and by any
 * plain `<a>` that needs the identical look without going through an Inertia
 * `<Link>` (e.g. a PDF-download link, where routing through `<Link>` risks
 * Inertia's non-Inertia-response fallback re-triggering an expensive server
 * render — see Rows/Index.vue's QR export).
 */
export const pillLinkClass =
    'inline-flex items-center gap-1 rounded-md px-3 py-1 text-xs font-medium text-white';

export const primaryPillVariantClass =
    'bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600';
