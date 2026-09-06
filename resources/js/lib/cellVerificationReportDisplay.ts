import type { CellVerificationSnapshot } from '@/types/admin';

export function snapshotProductLabel(
    snapshot: CellVerificationSnapshot,
): string | null {
    return snapshot.product?.name ?? null;
}
