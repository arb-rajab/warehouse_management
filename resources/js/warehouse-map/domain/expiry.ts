export type ExpiryTone = 'ok' | 'soon' | 'bad';

export interface ExpiryInfo {
    tone: ExpiryTone;
    /** Days left, or days since it expired (always positive). */
    days: number;
    expired: boolean;
}

export const EXPIRY_SOON_DAYS = 30;

export function expiryInfo(expiresInDays: number | null): ExpiryInfo | null {
    if (expiresInDays === null) {
        return null;
    }

    const expired = expiresInDays < 0;
    let tone: ExpiryTone = 'ok';

    if (expired) {
        tone = 'bad';
    } else if (expiresInDays <= EXPIRY_SOON_DAYS) {
        tone = 'soon';
    }

    return { tone, days: Math.abs(expiresInDays), expired };
}

/** Share of a pallet's boxes that are left, 0..100, or null when the server did not say. */
export function remainingPercent(
    remaining: number | null,
    total: number | null,
): number | null {
    if (remaining === null || !total) {
        return null;
    }

    return Math.round((100 * remaining) / total);
}

/** The gauge colour band on an opened pallet: green above half, amber from a quarter, red below. */
export function gaugeBand(fraction: number): 'high' | 'mid' | 'low' {
    if (fraction > 0.5) {
        return 'high';
    }

    return fraction > 0.25 ? 'mid' : 'low';
}

/** Deterministic hue (0..359) a product keeps in the drawer icon, label stripe and cargo tint. */
export function productHue(productId: number): number {
    return (productId * 47) % 360;
}
