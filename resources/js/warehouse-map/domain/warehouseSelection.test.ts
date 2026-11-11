import { describe, expect, it } from 'vitest';
import {
    normalizeWarehouseIds,
    sameWarehouseIds,
    warehouseSelectionLabel,
} from './warehouseSelection';

const options = [
    { id: 3, name: 'Annex' },
    { id: 1, name: 'Main' },
    { id: 7, name: 'Cold store' },
];
const t = (key: string, params?: Record<string, string | number>) =>
    params ? `${key}(${Object.values(params).join(',')})` : key;

describe('normalizeWarehouseIds', () => {
    it("keeps known ids once, in the switcher's order", () => {
        expect(normalizeWarehouseIds([7, 3, 7, 99], options)).toEqual([3, 7]);
    });

    it('turns nothing ticked into every warehouse', () => {
        expect(normalizeWarehouseIds([], options)).toBeNull();
        expect(normalizeWarehouseIds([99], options)).toBeNull();
    });

    it('turns every warehouse ticked into every warehouse, so the link stays short', () => {
        expect(normalizeWarehouseIds([1, 3, 7], options)).toBeNull();
    });
});

describe('sameWarehouseIds', () => {
    it('compares the warehouses, not their order', () => {
        expect(sameWarehouseIds([1, 3], [3, 1])).toBe(true);
        expect(sameWarehouseIds([1, 3], [1])).toBe(false);
        expect(sameWarehouseIds(null, null)).toBe(true);
        expect(sameWarehouseIds(null, [1])).toBe(false);
        expect(sameWarehouseIds([1], null)).toBe(false);
    });
});

describe('warehouseSelectionLabel', () => {
    it('names every warehouse, the one warehouse, or how many', () => {
        expect(warehouseSelectionLabel(options, null, t)).toBe('warehousesAll');
        expect(warehouseSelectionLabel(options, [], t)).toBe('warehousesAll');
        expect(warehouseSelectionLabel(options, [7], t)).toBe('Cold store');
        expect(warehouseSelectionLabel(options, [1, 7], t)).toBe(
            'warehousesCount(2)',
        );
    });

    it('counts a warehouse it has no name for', () => {
        expect(warehouseSelectionLabel(options, [42], t)).toBe(
            'warehousesCount(1)',
        );
    });
});
