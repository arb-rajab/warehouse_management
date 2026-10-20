import { parseTerms } from './SlotFilter';
import type { ProductOption, Slot } from './types';

/**
 * Every product the slots hold, once, with how many slots hold it — what the
 * product picker offers, so it never lists a product the map could not show.
 * In id order; the picker sorts by the name shown in the current language.
 */
export function listProductOptions(slots: readonly Slot[]): ProductOption[] {
    const byId = new Map<number, ProductOption>();

    for (const { pallet } of slots) {
        if (!pallet) {
            continue;
        }

        const option = byId.get(pallet.productId);

        if (option) {
            option.pallets += 1;
            continue;
        }

        byId.set(pallet.productId, {
            id: pallet.productId,
            name: pallet.productName,
            arName: pallet.productArName,
            active: pallet.productActive,
            pallets: 1,
        });
    }

    return [...byId.values()].sort((a, b) => a.id - b.id);
}

/** The options whose names (in either language) or id contain every typed word. */
export function filterProductOptions(
    options: readonly ProductOption[],
    query: string,
): ProductOption[] {
    const words = parseTerms(query.replace(/#/g, ' '));

    if (words.length === 0) {
        return [...options];
    }

    return options.filter((option) => {
        const haystack =
            `${option.name} ${option.arName} ${option.id}`.toLowerCase();

        return words.every((word) => haystack.includes(word));
    });
}
