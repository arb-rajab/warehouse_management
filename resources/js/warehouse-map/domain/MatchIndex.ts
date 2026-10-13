import { isFiltering, slotMatches } from './SlotFilter';
import type { MatchIndex, Slot, SlotFilter } from './types';

const EMPTY_INDEX: MatchIndex = {
    filtering: false,
    ids: new Set(),
    count: 0,
    byRow: new Map(),
    byLevel: new Map(),
};

/**
 * Counts which slots match the filter, in total and per row and per level:
 * the numbers on the aisle chips, the floor strip and the result pill.
 */
export function buildMatchIndex(
    slots: readonly Slot[],
    filter: SlotFilter,
): MatchIndex {
    if (!isFiltering(filter)) {
        return EMPTY_INDEX;
    }

    const ids = new Set<number>();
    const byRow = new Map<string, number>();
    const byLevel = new Map<number, number>();

    for (const slot of slots) {
        if (!slotMatches(slot, filter)) {
            continue;
        }

        ids.add(slot.id);
        byRow.set(slot.row, (byRow.get(slot.row) ?? 0) + 1);
        byLevel.set(slot.level, (byLevel.get(slot.level) ?? 0) + 1);
    }

    return { filtering: true, ids, count: ids.size, byRow, byLevel };
}

/** Whether a slot should be drawn as a match (everything matches while nothing is filtered). */
export function isMatch(index: MatchIndex, slot: Slot): boolean {
    return !index.filtering || index.ids.has(slot.id);
}
