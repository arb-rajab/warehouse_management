import { buildMatchIndex } from '../domain/MatchIndex';
import type { MatchIndex, Slot, SlotFilter, Warehouse } from '../domain/types';
import type { MapState, RackRef } from './state';

/**
 * The match index is rebuilt only when the warehouse or the filter changed:
 * the selector remembers its last inputs.
 */
export function createMatchSelector(): (state: MapState) => MatchIndex {
    let lastWarehouse: Warehouse | null = null;
    let lastFilter: SlotFilter | null = null;
    let lastIndex: MatchIndex = buildMatchIndex([], {
        terms: [],
        state: 'all',
        expiring: false,
    });

    return (state) => {
        if (state.warehouse !== lastWarehouse || state.filter !== lastFilter) {
            lastWarehouse = state.warehouse;
            lastFilter = state.filter;
            lastIndex = buildMatchIndex(
                state.warehouse?.slots ?? [],
                state.filter,
            );
        }

        return lastIndex;
    };
}

export const selectTarget = (state: MapState): Slot | null =>
    state.targetId === null
        ? null
        : (state.warehouse?.byId.get(state.targetId) ?? null);

export const selectHover = (state: MapState): Slot | null =>
    state.scene.hoverId === null
        ? null
        : (state.warehouse?.byId.get(state.scene.hoverId) ?? null);

export const selectSelected = (state: MapState): Slot | null =>
    state.scene.selectedId === null
        ? null
        : (state.warehouse?.byId.get(state.scene.selectedId) ?? null);

export const selectAisleCount = (state: MapState): number =>
    Math.ceil((state.warehouse?.rows.length ?? 0) / 2);

/** Which set of key hints applies right now. */
export type KeyHintKind = 'plan' | 'overview' | 'free' | 'guided';

export function selectKeyHintKind(state: MapState): KeyHintKind {
    if (state.view === '2d') {
        return 'plan';
    }

    return state.scene.mode === 'overview' ? 'overview' : state.scene.nav;
}

export const selectRack = (state: MapState): RackRef | null => state.rack;
