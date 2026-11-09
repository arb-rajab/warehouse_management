/**
 * The shared "pill" sizing/layout for a table-row action link and its two
 * variants: the outlined secondary "primary" (a brand-red solid button never
 * sits in every row) and the soft "danger" (never a solid red next to the
 * brand red). Used by TableActionLink.vue and by any
 * plain `<a>` that needs the identical look without going through an Inertia
 * `<Link>` (e.g. a PDF-download link, where routing through `<Link>` risks
 * Inertia's non-Inertia-response fallback re-triggering an expensive server
 * render — see Rows/Index.vue's QR export).
 */
export const pillLinkClass =
    'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-semibold';

export const primaryPillVariantClass =
    'border-line-strong bg-surface text-ink hover:bg-subtle';

export const dangerPillVariantClass =
    'border-transparent bg-danger-soft text-danger-strong hover:bg-danger-soft-hover';
