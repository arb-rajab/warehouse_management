import { addDays, subtractDays } from '@/lib/date';
import type { Cell, CellPallet } from '@/types/admin';

/**
 * The minimal cell shape these status checks need — satisfied by a full
 * `Cell` and by the lighter per-flat `CellHighlightSample` the cell map loads
 * for every flat to compute highlight-match counts (see cellHighlight.ts).
 */
export interface MatchableCell {
    state: Cell['state'];
    pallet: Pick<
        CellPallet,
        'product_id' | 'expiration_date' | 'added_at'
    > | null;
}

/**
 * Whether a cell's pallet expires within `withinDays` days from `today`,
 * inclusive of today but excluding anything already past due — an
 * already-expired pallet does NOT match, use `isCellExpired` for that.
 * Anchored on the server-provided `today`, never the browser clock. There is
 * no default threshold: `withinDays === null` means "not checking for
 * expiry", so this always returns false.
 */
export function isCellExpiringWithin(
    cell: MatchableCell | null,
    today: string,
    withinDays: number | null,
): boolean {
    if (!cell?.pallet || withinDays === null) {
        return false;
    }

    return (
        cell.pallet.expiration_date >= today &&
        cell.pallet.expiration_date <= addDays(today, withinDays)
    );
}

/**
 * Whether a cell's pallet's expiration date has already passed as of
 * `today` — anchored on the server-provided `today`, never the browser
 * clock. A pallet expiring today is not yet expired.
 */
export function isCellExpired(
    cell: MatchableCell | null,
    today: string,
): boolean {
    if (!cell?.pallet) {
        return false;
    }

    return cell.pallet.expiration_date < today;
}

/**
 * Whether a cell's pallet has been stored for at least `staleAfterDays` days,
 * anchored on the server-provided `today` — never the browser clock. There is
 * no default threshold: `staleAfterDays === null` means "not checking for
 * staleness", so this always returns false.
 */
export function isCellStale(
    cell: MatchableCell | null,
    today: string,
    staleAfterDays: number | null,
): boolean {
    if (!cell?.pallet || staleAfterDays === null) {
        return false;
    }

    return (
        cell.pallet.added_at.slice(0, 10) <= subtractDays(today, staleAfterDays)
    );
}
