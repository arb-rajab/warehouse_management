import { describe, expect, it } from 'vitest';
import { WarehouseGeometry } from './geometry';
import { LAYOUT } from './layout';

// 4 rows (2 aisles) x 10 columns x 3 levels
const geo = new WarehouseGeometry(4, 10, 3);

describe('WarehouseGeometry', () => {
    it('pairs rows into aisles', () => {
        expect(geo.aisles).toBe(2);
        expect(new WarehouseGeometry(5, 4, 2).aisles).toBe(3);
        expect(geo.aisleRows(1)).toEqual([2, 3]);
    });

    it("puts the first aisle on the viewer's right (smaller x)", () => {
        expect(geo.aisleCx(0)).toBeCloseTo(-LAYOUT.aisleGap / 2);
        expect(geo.aisleCx(1)).toBeCloseTo(LAYOUT.aisleGap / 2);
    });

    it('stands row A right of the aisle centre line and row B left of it, when walking in', () => {
        // walking in means looking along +z, where the viewer's right is -x
        expect(geo.rowX(0)).toBeCloseTo(geo.aisleCx(0) - LAYOUT.aisleWidth / 2);
        expect(geo.rowX(1)).toBeCloseTo(geo.aisleCx(0) + LAYOUT.aisleWidth / 2);
        expect(geo.rowSide(0)).toBe(-1);
        expect(geo.rowSide(1)).toBe(1);
    });

    it('orders every row from right to left: A, B | C, D', () => {
        const xs = [0, 1, 2, 3].map((i) => geo.rowX(i));

        expect(xs).toEqual([...xs].sort((p, q) => p - q));
    });

    it('starts column 1 at the entrance and spaces bays by 1.6 m', () => {
        expect(geo.zOf(1)).toBe(0);
        expect(geo.zOf(3)).toBeCloseTo(3.2);
        expect(geo.zMin).toBeCloseTo(-0.8);
        expect(geo.zMax).toBeCloseTo(geo.zOf(10) + 0.8);
    });

    it('stacks levels 1.15 m apart on the base beam', () => {
        expect(geo.beamY(1)).toBeCloseTo(LAYOUT.beamBase);
        expect(geo.beamY(3)).toBeCloseTo(
            LAYOUT.beamBase + 2 * LAYOUT.levelHeight,
        );
        expect(geo.height).toBeCloseTo(
            LAYOUT.beamBase + 3 * LAYOUT.levelHeight + LAYOUT.topClear,
        );
    });

    it('finds the centre of a slot', () => {
        const c = geo.slotCenter(1, 4, 2);

        expect(c.x).toBeCloseTo(geo.rowX(1));
        expect(c.z).toBeCloseTo(geo.zOf(4));
        expect(c.y).toBeCloseTo(geo.beamY(2) + 0.55);
    });

    it('finds the aisle nearest to an x', () => {
        expect(geo.aisleOf(geo.aisleCx(1) + 1)).toBe(1);
        expect(geo.aisleOf(geo.aisleCx(0) - 1)).toBe(0);
    });

    it('describes eye heights per shelf and finds the nearest', () => {
        expect(geo.levelEye(0)).toBeCloseTo(geo.beamY(1) + 0.85);
        expect(geo.nearestEye(geo.levelEye(2) + 0.1)).toBe(2);
        expect(geo.nearestEye(0)).toBe(0);
    });

    it('treats racks as walls below the top beam, but flyable above it', () => {
        const x = geo.rowX(0);
        const z = geo.zOf(5);

        expect(geo.isBlocked(x, z, 1.7)).toBe(true);
        expect(geo.isBlocked(x, z, geo.height + 1)).toBe(false);
        expect(geo.isBlocked(geo.aisleCx(0), z, 1.7)).toBe(false);
        expect(geo.overRack(x, z)).toBe(true);
        expect(geo.overRack(geo.aisleCx(0), z)).toBe(false);
    });

    it('keeps you near the building', () => {
        expect(geo.isBlocked(geo.bounds.maxX + 13, 0, 1.7)).toBe(true);
        expect(geo.isBlocked(0, geo.bounds.minZ - 7, 1.7)).toBe(true);
    });

    it('gives a farther fit distance for a longer warehouse', () => {
        const long = new WarehouseGeometry(4, 60, 3);

        expect(long.fitDistance(1.6)).toBeGreaterThan(geo.fitDistance(1.6));
        expect(geo.fitDistance(0.5)).toBeGreaterThan(geo.fitDistance(2));
    });
});
