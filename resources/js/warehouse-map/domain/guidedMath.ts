import type { WarehouseGeometry } from './geometry';

export interface WalkPose {
    x: number;
    y: number;
    z: number;
    yaw: number;
    pitch: number;
}

const QUARTER = Math.PI / 2;

export const round4 = (value: number): number => Math.round(value * 1e4) / 1e4;

/** The nearest of the four facings that stand square to the racks. */
export function quarter(yaw: number): number {
    return Math.round(yaw / QUARTER) * QUARTER;
}

/** Wraps an angle into (-PI, PI]. */
export function wrapAngle(angle: number): number {
    return Math.atan2(Math.sin(angle), Math.cos(angle));
}

/** The bay lines at the middle of an aisle and one bay either side of it. */
export function snapX(geometry: WarehouseGeometry, x: number): number {
    const cx = geometry.aisleCx(geometry.aisleOf(x));
    const spacing = geometry.layout.colSpacing;
    const offset = Math.max(-1, Math.min(1, Math.round((x - cx) / spacing)));

    return cx + offset * spacing;
}

export function snapZ(geometry: WarehouseGeometry, z: number): number {
    return (
        Math.round(z / geometry.layout.colSpacing) * geometry.layout.colSpacing
    );
}

export interface GuidedStep {
    x: number;
    z: number;
    yaw: number;
    /** How many bays the step actually covers (a long step shortens itself). */
    bays: number;
}

/**
 * One guided step: `forward` bays along the facing and `strafe` bays to its
 * right. The cart rolls bay by bay and stops before the first obstacle, so a
 * long step shortens itself and can never jump a rack; null means even the
 * first bay is blocked (the cart bumps).
 */
export function planGuidedStep(
    geometry: WarehouseGeometry,
    pose: WalkPose,
    forward: number,
    strafe: number,
): GuidedStep | null {
    const yaw = quarter(pose.yaw);
    const fx = Math.sin(yaw);
    const fz = Math.cos(yaw);
    const rx = -Math.cos(yaw);
    const rz = Math.sin(yaw);
    const spacing = geometry.layout.colSpacing;
    const sf = Math.sign(forward);
    const sr = Math.sign(strafe);
    const wanted = Math.max(Math.abs(forward), Math.abs(strafe));
    let best: GuidedStep | null = null;

    for (let bays = 1; bays <= wanted; bays++) {
        const x = round4(pose.x + (fx * sf + rx * sr) * spacing * bays);
        const z = round4(pose.z + (fz * sf + rz * sr) * spacing * bays);

        if (geometry.isBlocked(x, z, pose.y)) {
            break;
        }

        best = { x, z, yaw, bays };
    }

    return best;
}

/** The direction a blocked step bumps towards (unit-ish, in x/z). */
export function bumpDirection(
    pose: WalkPose,
    forward: number,
    strafe: number,
): { dx: number; dz: number } {
    const yaw = quarter(pose.yaw);
    const sf = Math.sign(forward);
    const sr = Math.sign(strafe);

    return {
        dx: Math.sin(yaw) * sf + -Math.cos(yaw) * sr,
        dz: Math.cos(yaw) * sf + Math.sin(yaw) * sr,
    };
}

/** The shelf index (0-based) after moving `delta` shelves, clamped to the rack. */
export function nextLevelIndex(
    geometry: WarehouseGeometry,
    current: number,
    delta: number,
): number {
    return Math.max(0, Math.min(geometry.levels - 1, current + delta));
}

/** Where the cart ends up after snapping to the nearest bay, shelf height and facing. */
export function snappedPose(
    geometry: WarehouseGeometry,
    pose: WalkPose,
): WalkPose & { levelIndex: number } {
    const levelIndex = geometry.nearestEye(pose.y);

    return {
        x: snapX(geometry, pose.x),
        y: geometry.levelEye(levelIndex),
        z: snapZ(geometry, pose.z),
        yaw: quarter(pose.yaw),
        pitch: 0,
        levelIndex,
    };
}
