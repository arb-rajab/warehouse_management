import { buildMatchIndex } from '../domain/MatchIndex';
import { columnSlots } from '../domain/normalizeRows';
import { listProductOptions } from '../domain/productOptions';
import type { SlotFilter, Warehouse } from '../domain/types';
import type { WarehouseSelection } from '../domain/warehouseSelection';
import type { MapState, RackRef } from './state';

/**
 * What the map keeps when it shows another set of warehouses: the search, the
 * chips, the floor, the target, the 3D selection, the open rack and the
 * matched-pallets drawer. Cells are named by their database ids, so whatever
 * points at a cell the new rows no longer hold is dropped rather than moved
 * onto another warehouse's cell with the same letter.
 */
export interface MapCarry {
    warehouseIds: WarehouseSelection;
    query: string;
    filter: SlotFilter;
    floor: number | null;
    targetId: number | null;
    selectedId: number | null;
    rack: RackRef | null;
    /** The cells the open rack showed: it stays open only while one of them is drawn. */
    rackSlotIds: readonly number[];
    drawerOpen: boolean;
}

/** What `restoreCarry` hands back to the store. */
export type RestoredCarry = Pick<
    MapState,
    'query' | 'filter' | 'floor' | 'targetId' | 'rack' | 'drawerOpen'
> & { selectedId: number | null };

export function carryOf(state: MapState): MapCarry {
    const rackSlotIds =
        state.rack && state.warehouse
            ? columnSlots(state.warehouse, state.rack.row, state.rack.col).map(
                  (slot) => slot.id,
              )
            : [];

    return {
        warehouseIds: state.warehouseIds,
        query: state.query,
        filter: state.filter,
        floor: state.floor,
        targetId: state.targetId,
        selectedId: state.scene.selectedId,
        rack: state.rack,
        rackSlotIds,
        drawerOpen: state.drawerOpen,
    };
}

/** The carried state, minus whatever the new rows no longer hold. */
export function restoreCarry(
    carry: MapCarry,
    warehouse: Warehouse,
): RestoredCarry {
    const held = (id: number | null) =>
        id !== null && warehouse.byId.has(id) ? id : null;
    const products = new Set(
        listProductOptions(warehouse.slots).map((option) => option.id),
    );
    const productIds = carry.filter.productIds.filter((id) => products.has(id));
    const filter: SlotFilter =
        productIds.length === carry.filter.productIds.length
            ? carry.filter
            : { ...carry.filter, productIds };
    const rackKept =
        carry.rack !== null &&
        carry.rackSlotIds.some((id) => warehouse.byId.has(id));

    return {
        query: carry.query,
        filter,
        floor:
            carry.floor !== null && carry.floor <= warehouse.levels
                ? carry.floor
                : null,
        targetId: held(carry.targetId),
        selectedId: held(carry.selectedId),
        rack: rackKept ? carry.rack : null,
        // the drawer lists the matched pallets: none left, nothing to list
        drawerOpen:
            carry.drawerOpen &&
            buildMatchIndex(warehouse.slots, filter).count > 0,
    };
}

/**
 * Whether the 3D camera can stay where it is on the new rows: the selected
 * slot is still drawn, and a walker's aisle still holds the same two rows.
 * Otherwise it returns to the overview.
 */
export function keepsCamera(
    before: Pick<MapState, 'warehouse' | 'scene'>,
    after: Pick<MapState, 'warehouse' | 'scene'>,
): boolean {
    if (before.scene.selectedId !== null && after.scene.selectedId === null) {
        return false;
    }

    const aisle = before.scene.activeAisle;

    if (before.scene.mode === 'overview' || aisle === null) {
        return true;
    }

    const rowsOf = (warehouse: Warehouse | null) =>
        [aisle * 2, aisle * 2 + 1].map((i) => warehouse?.rows[i]?.id ?? null);
    const [a, b] = [rowsOf(before.warehouse), rowsOf(after.warehouse)];

    return a[0] !== null && a[0] === b[0] && a[1] === b[1];
}
