import { productName } from '@/lib/productName';
import type { CellVerificationSnapshot } from '@/types/admin';

export function snapshotProductLabel(
    snapshot: CellVerificationSnapshot,
): string | null {
    const product = snapshot.product;

    return product ? productName(product.name, product.ar_name) : null;
}
