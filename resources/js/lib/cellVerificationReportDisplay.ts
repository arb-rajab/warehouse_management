import { router } from '@inertiajs/vue3';
import { accept as acceptReportAction } from '@/actions/App/Http/Controllers/Admin/CellVerificationReportController';
import { confirmAcceptVerificationReport } from '@/lib/confirm';
import { formatSlot } from '@/lib/location';
import { productName } from '@/lib/productName';
import type {
    CellVerificationReport,
    CellVerificationSnapshot,
} from '@/types/admin';

export function snapshotProductLabel(
    snapshot: CellVerificationSnapshot,
): string | null {
    const product = snapshot.product;

    return product ? productName(product.name, product.ar_name) : null;
}

/**
 * Only an unaccepted mismatch can be accepted, and only once its round is
 * finished — until then the round's rows are frozen against pallet changes.
 */
export function canAcceptReport(report: CellVerificationReport): boolean {
    return (
        report.round_completed === true &&
        !report.is_correct &&
        report.accepted_at === null
    );
}

/**
 * `returnTo` tells the backend which page to redirect back to — this action is
 * triggered from both CellVerificationRounds/Show.vue (the default, omitted)
 * and Users/Show.vue ('user'), which the Referer header/session can't reliably
 * distinguish (see AcceptCellVerificationReportRequest).
 */
export function acceptReport(
    report: CellVerificationReport,
    returnTo?: 'user',
): void {
    const slot = formatSlot(
        report.cell.row_letter,
        report.cell.cell_number,
        report.cell.flat_number,
    );

    if (!confirmAcceptVerificationReport(slot)) {
        return;
    }

    router.post(
        acceptReportAction(
            { cellVerificationReport: report.id },
            { mergeQuery: {} },
        ).url,
        { return_to: returnTo ?? null },
        { preserveScroll: true },
    );
}
