import { LAYOUT } from './layout';
import type { Layout } from './layout';

export interface Point3 {
    x: number;
    y: number;
    z: number;
}

export interface Wall {
    x0: number;
    x1: number;
    z0: number;
    z1: number;
}

export interface Bounds {
    minX: number;
    maxX: number;
    minZ: number;
    maxZ: number;
    w: number;
    d: number;
    cx: number;
    cz: number;
}

/**
 * Where everything stands in the 3D model. Everything looks in from the
 * entrance end (+z); the first aisle is on the viewer's left, rows A-B share
 * one aisle with A on the left when you walk in.
 */
export class WarehouseGeometry {
    readonly aisles: number;
    /** Height of the rack frame. */
    readonly height: number;
    readonly zMin: number;
    readonly zMax: number;
    readonly bounds: Bounds;
    readonly walls: Wall[];

    constructor(
        readonly rowCount: number,
        readonly cols: number,
        readonly levels: number,
        readonly layout: Layout = LAYOUT,
    ) {
        this.aisles = Math.ceil(rowCount / 2);
        this.height =
            layout.beamBase + levels * layout.levelHeight + layout.topClear;
        this.zMin = -layout.colSpacing * 0.5;
        this.zMax = this.zOf(cols) + layout.colSpacing * 0.5;

        const xs = Array.from({ length: rowCount }, (_, i) => this.rowX(i));
        const minX = (xs.length ? Math.min(...xs) : 0) - 6;
        const maxX = (xs.length ? Math.max(...xs) : 0) + 6;
        const minZ = this.zMin - layout.apron;
        const maxZ = this.zMax + 6;

        this.bounds = {
            minX,
            maxX,
            minZ,
            maxZ,
            w: maxX - minX,
            d: maxZ - minZ,
            cx: (minX + maxX) / 2,
            cz: (this.zMin + this.zMax) / 2,
        };

        this.walls = xs.map((x) => ({
            x0: x - layout.rackDepth / 2 - 0.38,
            x1: x + layout.rackDepth / 2 + 0.38,
            z0: this.zMin - 0.3,
            z1: this.zMax + 0.3,
        }));
    }

    aisleCx(aisle: number): number {
        return ((this.aisles - 1) / 2 - aisle) * this.layout.aisleGap;
    }

    /** Rows 0,2,4.. stand on the +x side of their aisle, rows 1,3,5.. on the -x side. */
    rowSide(rowIdx: number): 1 | -1 {
        return rowIdx % 2 === 0 ? 1 : -1;
    }

    rowX(rowIdx: number): number {
        return (
            this.aisleCx(rowIdx >> 1) +
            this.rowSide(rowIdx) * (this.layout.aisleWidth / 2)
        );
    }

    /** Column 1 is at the entrance end. */
    zOf(col: number): number {
        return (col - 1) * this.layout.colSpacing;
    }

    /** Height of the beam a level rests on (levels are 1-based). */
    beamY(level: number): number {
        return this.layout.beamBase + (level - 1) * this.layout.levelHeight;
    }

    /** The middle of a slot's pallet space. */
    slotCenter(rowIdx: number, col: number, level: number): Point3 {
        return {
            x: this.rowX(rowIdx),
            y: this.beamY(level) + 0.05 + 0.5,
            z: this.zOf(col),
        };
    }

    /** Indices of the two rows an aisle serves. */
    aisleRows(aisle: number): [number, number] {
        return [aisle * 2, aisle * 2 + 1];
    }

    aisleOf(x: number): number {
        let best = 0;
        let bestDistance = Infinity;

        for (let a = 0; a < this.aisles; a++) {
            const distance = Math.abs(x - this.aisleCx(a));

            if (distance < bestDistance) {
                bestDistance = distance;
                best = a;
            }
        }

        return best;
    }

    /** Distance at which the whole warehouse fits the window, whatever its shape. */
    fitDistance(aspect: number): number {
        const tv = Math.tan((this.layout.fovOver * Math.PI) / 360);
        const asp = aspect || 1.6;

        return (
            Math.max(
                (this.bounds.w / (2 * tv * asp)) * 1.32,
                (this.bounds.d / (2 * tv)) * 1.1,
            ) + 6
        );
    }

    /** Eye height that looks at the middle of a shelf (0-based index). */
    levelEye(index: number): number {
        return this.beamY(index + 1) + 0.05 + 0.8;
    }

    nearestEye(y: number): number {
        let best = 0;

        for (let i = 1; i < this.levels; i++) {
            if (
                Math.abs(this.levelEye(i) - y) <
                Math.abs(this.levelEye(best) - y)
            ) {
                best = i;
            }
        }

        return best;
    }

    /** Whether a point is over a rack footprint (whatever its height). */
    overRack(x: number, z: number): boolean {
        return this.walls.some(
            (w) => x > w.x0 && x < w.x1 && z > w.z0 && z < w.z1,
        );
    }

    /**
     * Every rack is a wall below its top beam: you cannot walk through it, but
     * you can fly over it. The outer limits keep you near the building.
     */
    isBlocked(x: number, z: number, y: number): boolean {
        const b = this.bounds;

        if (
            x < b.minX - 12 ||
            x > b.maxX + 12 ||
            z < b.minZ - 6 ||
            z > b.maxZ + 8
        ) {
            return true;
        }

        return y < this.height + 0.35 && this.overRack(x, z);
    }
}
