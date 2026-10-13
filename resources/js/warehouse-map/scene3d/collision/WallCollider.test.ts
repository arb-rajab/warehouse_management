import { describe, expect, it } from 'vitest';
import { WarehouseGeometry } from '../../domain/geometry';
import { WallCollider } from './WallCollider';

const geo = new WarehouseGeometry(4, 10, 3);
const collider = new WallCollider(geo);
const aisle = geo.aisleCx(0);

describe('WallCollider', () => {
    it('walks freely along an aisle', () => {
        const body = { x: aisle, y: 1.7, z: 2 };

        collider.move(body, 0, 0, 3, 1);

        expect(body.z).toBeCloseTo(5);
        expect(body.x).toBeCloseTo(aisle);
    });

    it('cannot walk into a rack', () => {
        const body = { x: aisle + 3, y: 1.7, z: 4 };

        collider.move(body, 0.5, 0, 0, 1);

        expect(body.x).toBeCloseTo(aisle + 3);
    });

    it('slides along a rack instead of sticking to it', () => {
        const body = { x: aisle + 3, y: 1.7, z: 4 };

        collider.move(body, 0.5, 0, 2, 1);

        expect(body.x).toBeCloseTo(aisle + 3);
        expect(body.z).toBeCloseTo(6);
    });

    it('can fly over a rack, and glides above it while over it', () => {
        const body = { x: aisle + 3, y: geo.height + 1, z: 4 };

        collider.move(body, 5, 0, 0, 1);

        expect(body.x).toBeGreaterThan(aisle + 3);

        const low = { x: aisle + 4, y: geo.height + 1, z: 4 };

        collider.move(low, 0, -50, 0, 1);
        expect(low.y).toBeGreaterThanOrEqual(geo.height + 0.5);
    });

    it('keeps the eye between the floor and the ceiling', () => {
        const body = { x: aisle, y: 1.7, z: 2 };

        collider.move(body, 0, -100, 0, 1);
        expect(body.y).toBeCloseTo(0.9);

        collider.move(body, 0, 100, 0, 1);
        expect(body.y).toBeCloseTo(geo.height + geo.layout.ceilClear - 0.5);
    });

    it('stops at the edge of the building', () => {
        const body = { x: aisle, y: 1.7, z: geo.bounds.maxZ + 7 };

        collider.move(body, 0, 0, 50, 1);

        expect(body.z).toBeCloseTo(geo.bounds.maxZ + 7);
    });
});
