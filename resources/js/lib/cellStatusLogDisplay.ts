import { router } from '@inertiajs/vue3';
import { acknowledgeFlags as acknowledgeFlagsAction } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { t } from '@/lib/i18n';
import type { CellSlotLocation, CellStatusLog } from '@/types/admin';

export type DisplayCellStatusLog = CellStatusLog & { pairedIn?: CellStatusLog };

export function cellLogActionLabel(action: CellStatusLog['action']): string {
    return t(`cellLog.actions.${action}`);
}

export function flagReasonLabel(
    reason: CellStatusLog['flags'][number]['reason'],
): string {
    return t(`cellLog.flags.reasons.${reason}`);
}

export function hasUnacknowledgedFlags(log: CellStatusLog): boolean {
    return log.flags.some((flag) => !flag.acknowledged);
}

/**
 * `returnTo` tells the backend which page to redirect back to — this action is
 * triggered from both CellStatusLogs/Index.vue (the default, omitted) and
 * Users/Show.vue ('user'), which the Referer header/session can't reliably
 * distinguish (see AcknowledgeCellStatusLogFlagsRequest).
 */
export function acknowledgeFlags(log: CellStatusLog, returnTo?: 'user'): void {
    router.post(
        acknowledgeFlagsAction(log, { mergeQuery: {} }).url,
        { return_to: returnTo ?? null },
        { preserveScroll: true },
    );
}

/**
 * The slot(s) a log row names: a transfer's two sides, else the log's cell —
 * null `from` only for a correction of a pallet with no cell yet.
 */
export function transferPair(log: CellStatusLog): {
    from: CellSlotLocation | null;
    to: CellSlotLocation | null;
} {
    if (log.action === 'transferred_in' && log.related_cell) {
        return { from: log.related_cell, to: log.cell };
    }

    return { from: log.cell, to: log.related_cell };
}

/**
 * Whether a log row is a record correction of the pallet's own data (its
 * product or its note) rather than a shelf action: it shows the old and new
 * values, and has no states when the pallet has no cell yet.
 */
export function isPalletCorrection(log: CellStatusLog): boolean {
    return log.action === 'product_changed' || log.action === 'note_changed';
}

/**
 * The warehouse name shown under a cell's slot in the log listings: only in a
 * system with several warehouses (`multipleWarehouses`), and only when the
 * payload names it. Both sides of a transfer ask separately, so a
 * same-warehouse transfer names it on both sides and a cross-warehouse one
 * shows the two differing names.
 */
export function cellWarehouseName(
    cell: CellSlotLocation,
    multipleWarehouses: boolean | undefined,
): string | null {
    return multipleWarehouses ? (cell.warehouse?.name ?? null) : null;
}

function sameCellLocation(
    a: CellSlotLocation | null,
    b: CellSlotLocation | null,
): boolean {
    return (
        a !== null &&
        b !== null &&
        a.warehouse?.id === b.warehouse?.id &&
        a.row_letter === b.row_letter &&
        a.cell_number === b.cell_number &&
        a.flat_number === b.flat_number
    );
}

function isTransferPair(out: CellStatusLog, incoming: CellStatusLog): boolean {
    return (
        incoming.action === 'transferred_in' &&
        out.pallet !== null &&
        incoming.pallet !== null &&
        out.pallet.id === incoming.pallet.id &&
        out.created_at === incoming.created_at &&
        sameCellLocation(out.cell, incoming.related_cell) &&
        sameCellLocation(out.related_cell, incoming.cell)
    );
}

/**
 * A transfer writes a `transferred_out` row (on the source cell) and a
 * `transferred_in` row (on the destination cell) in the same transaction.
 * Every column except the action label ends up identical between the two, so
 * when both sides are present in the given list, merge them into a single
 * entry keyed on the `transferred_out` side. A list showing only one side
 * (filtered to one cell, one pallet's history split across a page boundary,
 * or a single action) falls through unmerged with no special casing needed.
 */
export function mergeTransferPairs(
    logs: CellStatusLog[],
): DisplayCellStatusLog[] {
    const pairedInByOutId = new Map<number, CellStatusLog>();
    const pairedInIds = new Set<number>();

    for (const log of logs) {
        if (log.action !== 'transferred_out') {
            continue;
        }

        const incoming = logs.find(
            (candidate) =>
                !pairedInIds.has(candidate.id) &&
                isTransferPair(log, candidate),
        );

        if (incoming) {
            pairedInByOutId.set(log.id, incoming);
            pairedInIds.add(incoming.id);
        }
    }

    return logs
        .filter((log) => !pairedInIds.has(log.id))
        .map((log) => {
            const pairedIn = pairedInByOutId.get(log.id);

            return pairedIn ? { ...log, pairedIn } : log;
        });
}
