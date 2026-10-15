import type { Slot, SlotFilter } from './types';

/** A pallet counts as "expiring" when it runs out within this many days (or already has). */
export const EXPIRING_WITHIN_DAYS = 30;

export const NO_FILTER: SlotFilter = {
    terms: [],
    state: 'all',
    expiring: false,
};

/** Every word has to match, so the search box splits on whitespace. */
export function parseTerms(query: string): string[] {
    return query.toLowerCase().split(/\s+/).filter(Boolean);
}

export function isFiltering(filter: SlotFilter): boolean {
    return filter.state !== 'all' || filter.terms.length > 0 || filter.expiring;
}

/**
 * Decides whether one slot satisfies the state chips, the search words and the
 * expiring chip. An empty slot only matches when no pallet-level filter is on.
 */
export function slotMatches(slot: Slot, filter: SlotFilter): boolean {
    if (filter.state !== 'all' && slot.state !== filter.state) {
        return false;
    }

    const needsPallet = filter.terms.length > 0 || filter.expiring;

    if (slot.state === 'empty') {
        return !needsPallet;
    }

    if (needsPallet && !slot.pallet) {
        return false;
    }

    if (
        filter.terms.length > 0 &&
        !filter.terms.every((word) => slot.haystack.includes(word))
    ) {
        return false;
    }

    return (
        !filter.expiring ||
        (slot.expiresInDays !== null &&
            slot.expiresInDays <= EXPIRING_WITHIN_DAYS)
    );
}
