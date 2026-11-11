import { describe, expect, it } from 'vitest';
import { normalizeRows } from '../domain/normalizeRows';
import { listProductOptions } from '../domain/productOptions';
import { NO_FILTER } from '../domain/SlotFilter';
import type { ApiRow } from '../domain/types';
import { apiRows, makeWarehouse } from '../testing/fixtures';
import { createActions } from './actions';
import { carryOf, keepsCamera, restoreCarry } from './carry';
import type { MapCarry } from './carry';
import { initialState, INITIAL_SCENE } from './state';
import type { MapState } from './state';
import { Store } from './Store';

const now = new Date('2026-10-13T12:00:00').getTime();
/** Rows A-D: cells 1-18 in A, 19-36 in B, 37-54 in C, 55-72 in D. */
const everything = makeWarehouse(4, 6, 3);
/** Only rows C and D, as a filter on their warehouse would load them. */
const northOnly = normalizeRows(apiRows(4, 6, 3).slice(2), now);
/** Another warehouse with the same letters: no cell or row id in common. */
const elsewhere = normalizeRows(
    apiRows(4, 6, 3).map((row): ApiRow => ({
        ...row,
        id: row.id + 100,
        cells: row.cells?.map((cell) => ({ ...cell, id: cell.id + 1000 })),
    })),
    now,
);

function carry(overrides: Partial<MapCarry> = {}): MapCarry {
    return {
        warehouseIds: null,
        query: '',
        filter: { ...NO_FILTER },
        floor: null,
        targetId: null,
        selectedId: null,
        rack: null,
        rackSlotIds: [],
        drawerOpen: false,
        ...overrides,
    };
}

describe('carryOf', () => {
    it('takes the search, the chips, the floor, the target, the selection, the rack (with its cells) and the drawer', () => {
        const state = initialState({
            warehouse: everything,
            warehouseIds: [2],
            query: 'rice',
            filter: { ...NO_FILTER, terms: ['rice'], state: 'full' },
            floor: 2,
            targetId: 40,
            rack: { row: 'C', col: 1, level: 2 },
            drawerOpen: true,
            scene: { ...INITIAL_SCENE, selectedId: 41 },
        });

        expect(carryOf(state)).toEqual({
            warehouseIds: [2],
            query: 'rice',
            filter: { ...NO_FILTER, terms: ['rice'], state: 'full' },
            floor: 2,
            targetId: 40,
            selectedId: 41,
            rack: { row: 'C', col: 1, level: 2 },
            rackSlotIds: [37, 38, 39],
            drawerOpen: true,
        });
    });
});

describe('restoreCarry', () => {
    it('keeps the search, the chips and the floor whatever the warehouses', () => {
        const kept = restoreCarry(
            carry({
                query: 'rice',
                filter: {
                    ...NO_FILTER,
                    terms: ['rice'],
                    state: 'opened',
                    expiring: true,
                },
                floor: 3,
            }),
            elsewhere,
        );

        expect(kept.query).toBe('rice');
        expect(kept.filter).toMatchObject({
            terms: ['rice'],
            state: 'opened',
            expiring: true,
        });
        expect(kept.floor).toBe(3);
    });

    it('drops a floor the new rows do not reach', () => {
        expect(restoreCarry(carry({ floor: 4 }), everything).floor).toBeNull();
    });

    it('keeps the target, the selection and the rack while their cells are drawn', () => {
        const kept = restoreCarry(
            carry({
                targetId: 40,
                selectedId: 41,
                rack: { row: 'C', col: 1, level: 2 },
                rackSlotIds: [37, 38, 39],
            }),
            northOnly,
        );

        expect(kept).toMatchObject({
            targetId: 40,
            selectedId: 41,
            rack: { row: 'C', col: 1, level: 2 },
        });
    });

    it("drops them when the cells are gone, rather than opening another warehouse's rack with the same letter", () => {
        const kept = restoreCarry(
            carry({
                targetId: 2,
                selectedId: 3,
                rack: { row: 'A', col: 1, level: null },
                rackSlotIds: [1, 2, 3],
            }),
            elsewhere,
        );

        expect(kept).toMatchObject({
            targetId: null,
            selectedId: null,
            rack: null,
        });
    });

    it('keeps the checked products the new rows hold and forgets the others', () => {
        const held = listProductOptions(northOnly.slots)[0].id;
        const kept = restoreCarry(
            carry({ filter: { ...NO_FILTER, productIds: [held, 999_999] } }),
            northOnly,
        );

        expect(kept.filter.productIds).toEqual([held]);
    });

    it('keeps the very same filter when every checked product is still there', () => {
        const filter = { ...NO_FILTER, terms: ['rice'] };

        expect(restoreCarry(carry({ filter }), northOnly).filter).toBe(filter);
    });

    it('keeps the matched-pallets drawer open only while it has pallets to list', () => {
        const held = listProductOptions(northOnly.slots)[0].id;

        expect(
            restoreCarry(
                carry({
                    drawerOpen: true,
                    filter: { ...NO_FILTER, productIds: [held] },
                }),
                northOnly,
            ).drawerOpen,
        ).toBe(true);
        expect(
            restoreCarry(
                carry({
                    drawerOpen: true,
                    filter: { ...NO_FILTER, terms: ['no-such-product'] },
                }),
                northOnly,
            ).drawerOpen,
        ).toBe(false);
    });
});

describe('keepsCamera', () => {
    const at = (
        warehouse: MapState['warehouse'],
        scene: Partial<MapState['scene']> = {},
    ) => ({ warehouse, scene: { ...INITIAL_SCENE, ...scene } });

    it('keeps the overview camera', () => {
        expect(keepsCamera(at(everything), at(northOnly))).toBe(true);
    });

    it('returns to the overview when the selected slot is gone', () => {
        expect(
            keepsCamera(
                at(everything, { selectedId: 2 }),
                at(elsewhere, { selectedId: null }),
            ),
        ).toBe(false);
    });

    it('keeps a walker whose aisle still holds the same two rows', () => {
        const walking = { mode: 'walk' as const, activeAisle: 1 };

        expect(
            keepsCamera(at(everything, walking), at(everything, walking)),
        ).toBe(true);
    });

    it('returns a walker to the overview when other rows took the aisle', () => {
        const walking = { mode: 'walk' as const, activeAisle: 0 };

        expect(keepsCamera(at(everything, walking), at(northOnly))).toBe(false);
        expect(
            keepsCamera(
                at(everything, { ...walking, activeAisle: 1 }),
                at(northOnly),
            ),
        ).toBe(false);
    });
});

describe('showRows', () => {
    it('shows the new rows and keeps what they still hold, from the current state', () => {
        const store = new Store<MapState>(
            initialState({
                warehouse: everything,
                query: 'rice',
                filter: { ...NO_FILTER, terms: ['rice'] },
                targetId: 40,
                rack: { row: 'A', col: 1, level: null },
                scene: {
                    ...INITIAL_SCENE,
                    selectedId: 2,
                    hoverId: 41,
                    cardVisible: true,
                },
                loadFailed: true,
            }),
        );

        createActions(store).showRows(northOnly);

        const s = store.get();

        expect(s.warehouse).toBe(northOnly);
        expect(s.loadFailed).toBe(false);
        expect(s.query).toBe('rice');
        expect(s.targetId).toBe(40);
        expect(s.rack).toBeNull();
        expect(s.scene.selectedId).toBeNull();
        expect(s.scene.hoverId).toBe(41);
        expect(s.scene.cardVisible).toBe(true);
    });

    it('applies a carried state from an earlier mount, and drops a hover the rows no longer hold', () => {
        const store = new Store<MapState>(
            initialState({
                scene: { ...INITIAL_SCENE, hoverId: 5, cardVisible: true },
            }),
        );

        createActions(store).showRows(
            northOnly,
            carry({ query: 'oil', filter: { ...NO_FILTER, terms: ['oil'] } }),
        );

        expect(store.get().query).toBe('oil');
        expect(store.get().filter.terms).toEqual(['oil']);
        expect(store.get().scene.hoverId).toBeNull();
        expect(store.get().scene.cardVisible).toBe(false);
    });

    it('records the warehouses asked for', () => {
        const store = new Store<MapState>(initialState());

        createActions(store).setWarehouseIds([3, 4]);

        expect(store.get().warehouseIds).toEqual([3, 4]);
    });
});
