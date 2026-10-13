import type { WarehouseGeometry } from '../../domain/geometry';
import { clamp } from '../../utils/math';

export interface Body {
    x: number;
    y: number;
    z: number;
}

/**
 * Keeps the walker out of the racks: every rack row is a wall below its top
 * beam, so you cannot walk through one but can fly over it by lifting. Moving
 * along each axis separately lets you slide along a wall instead of sticking.
 */
export class WallCollider {
    constructor(private readonly geometry: WarehouseGeometry) {}

    /** Moves `body` by a velocity for `dt` seconds, sliding along walls. Mutates and returns it. */
    move(body: Body, vx: number, vy: number, vz: number, dt: number): Body {
        const geo = this.geometry;
        const b = geo.bounds;
        const ceiling = geo.height + geo.layout.ceilClear - 0.5;

        body.y = clamp(body.y + vy * dt, 0.9, ceiling);

        const nx = body.x + vx * dt;
        const nz = body.z + vz * dt;

        if (!geo.isBlocked(nx, body.z, body.y)) {
            body.x = clamp(nx, b.minX - 12, b.maxX + 12);
        }

        if (!geo.isBlocked(body.x, nz, body.y)) {
            body.z = clamp(nz, b.minZ - 6, b.maxZ + 8);
        }

        // over a rack you glide above it; you cannot sink into the shelves
        if (geo.overRack(body.x, body.z)) {
            body.y = Math.max(body.y, geo.height + 0.5);
        }

        return body;
    }
}
