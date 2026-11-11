import type { WarehouseOption } from './types';

/**
 * Which warehouses the map shows: `null` is every warehouse, otherwise the
 * ids in the order the switcher lists them.
 */
export type WarehouseSelection = readonly number[] | null;

/**
 * The selection the host is asked for: known ids only, in the switcher's
 * order, without duplicates. Nothing ticked, or every warehouse ticked, is
 * "All warehouses" (`null`), so the URL stays clean and a warehouse added
 * later is still shown.
 */
export function normalizeWarehouseIds(
    ids: Iterable<number>,
    options: readonly WarehouseOption[],
): WarehouseSelection {
    const wanted = new Set(ids);
    const kept = options.filter((o) => wanted.has(o.id)).map((o) => o.id);

    return kept.length === 0 || kept.length === options.length ? null : kept;
}

/** Whether two selections show the same warehouses. */
export function sameWarehouseIds(
    a: WarehouseSelection,
    b: WarehouseSelection,
): boolean {
    if (a === null || b === null) {
        return a === b;
    }

    const set = new Set(a);

    return a.length === b.length && b.every((id) => set.has(id));
}

/** "All warehouses", the one warehouse's name, or "3 warehouses". */
export function warehouseSelectionLabel(
    options: readonly WarehouseOption[],
    selected: WarehouseSelection,
    t: (key: string, params?: Record<string, string | number>) => string,
): string {
    if (selected === null || selected.length === 0) {
        return t('warehousesAll');
    }

    if (selected.length === 1) {
        return (
            options.find((o) => o.id === selected[0])?.name ??
            t('warehousesCount', { count: 1 })
        );
    }

    return t('warehousesCount', { count: selected.length });
}
