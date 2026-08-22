/**
 * World-space layout and first-person movement math for the 3D warehouse
 * map (CellMap3D.vue). Kept free of any three.js/WebGL dependency so it can
 * be unit-tested directly — CellMap3D.vue is the only place that turns these
 * plain numbers into an actual scene.
 *
 * World axes: X = row index (aisles side by side, in `rows` order), Z = cell
 * number (depth along a row), Y = flat number (stacked shelf levels). The
 * camera can fly freely along Y (Space/Shift) to view other flats directly,
 * in addition to pitching to look up/down without moving.
 */

export const ROW_SPACING = 4;
export const CELL_SPACING = 3;
export const FLAT_SPACING = 2.5;
export const EYE_HEIGHT = 1.7;
export const MIN_EYE_HEIGHT = 0.5;

export const WALK_SPEED = 4;

export const PITCH_MIN_DEGREES = -75;
export const PITCH_MAX_DEGREES = 75;
export const PITCH_DRAG_SENSITIVITY = 0.15;
export const YAW_DRAG_SENSITIVITY = 0.15;

export type MoveDirection =
    'forward' | 'backward' | 'left' | 'right' | 'up' | 'down';

const MOVE_KEYS: Record<string, MoveDirection> = {
    w: 'forward',
    arrowup: 'forward',
    s: 'backward',
    arrowdown: 'backward',
    a: 'left',
    arrowleft: 'left',
    d: 'right',
    arrowright: 'right',
    ' ': 'up',
    shift: 'down',
};

/** Maps a KeyboardEvent.key to the movement direction it drives, or null for any other key. */
export function moveDirectionForKey(key: string): MoveDirection | null {
    return MOVE_KEYS[key.toLowerCase()] ?? null;
}

export function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

export interface WalkerPosition {
    x: number;
    y: number;
    z: number;
}

export interface WalkerBounds {
    minX: number;
    maxX: number;
    minY: number;
    maxY: number;
    minZ: number;
    maxZ: number;
}

/**
 * Advances a walking position by `deltaSeconds` given the currently held
 * movement directions, clamped to the warehouse's bounds. Forward/backward
 * moves along Z (down the current row's aisle); left/right strafes along X
 * (into the neighboring row's aisle); up/down flies along Y (between flats).
 *
 * The camera always faces +Z with up = +Y, so its screen-right (local +X)
 * is world -X — a right-handed lookAt basis has `right = up × back`, and
 * `back` (eye-to-target reversed) is -Z here, giving `(0,1,0) × (0,0,-1) =
 * (-1,0,0)`. So pressing the "right" key must DECREASE world X to move
 * toward what actually appears on the right of the screen (and "left"
 * increases it) — get this backwards and left/right visually swap.
 */
export function stepPosition(
    position: WalkerPosition,
    pressedDirections: ReadonlySet<MoveDirection>,
    deltaSeconds: number,
    bounds: WalkerBounds,
    speed: number = WALK_SPEED,
): WalkerPosition {
    let { x, y, z } = position;
    const distance = speed * deltaSeconds;

    if (pressedDirections.has('forward')) {
        z += distance;
    }

    if (pressedDirections.has('backward')) {
        z -= distance;
    }

    if (pressedDirections.has('right')) {
        x -= distance;
    }

    if (pressedDirections.has('left')) {
        x += distance;
    }

    if (pressedDirections.has('up')) {
        y += distance;
    }

    if (pressedDirections.has('down')) {
        y -= distance;
    }

    return {
        x: clamp(x, bounds.minX, bounds.maxX),
        y: clamp(y, bounds.minY, bounds.maxY),
        z: clamp(z, bounds.minZ, bounds.maxZ),
    };
}

/**
 * Advances the camera's pitch (look up/down) by a vertical pointer-drag
 * delta in screen pixels, clamped to [PITCH_MIN_DEGREES, PITCH_MAX_DEGREES].
 * Dragging up (a negative screen-Y delta) looks up, matching the
 * non-inverted convention most first-person controls use.
 */
export function stepPitch(
    currentPitchDegrees: number,
    dragDeltaYPixels: number,
    sensitivity: number = PITCH_DRAG_SENSITIVITY,
): number {
    return clamp(
        currentPitchDegrees - dragDeltaYPixels * sensitivity,
        PITCH_MIN_DEGREES,
        PITCH_MAX_DEGREES,
    );
}

/** Wraps a degree value into [0, 360) — e.g. -10 becomes 350, 370 becomes 10. */
export function normalizeYaw(degrees: number): number {
    return ((degrees % 360) + 360) % 360;
}

/**
 * Advances the camera's yaw (look left/right, including all the way around
 * to look backward) by a horizontal pointer-drag delta in screen pixels.
 * Dragging right turns the view right, matching the non-inverted convention
 * `stepPitch` already uses for the vertical axis. Unlike pitch, yaw is not
 * clamped — it wraps freely all the way around.
 *
 * Movement (`stepPosition`) is deliberately NOT relative to yaw — W/S/A/D
 * always walk/strafe along the fixed grid axes regardless of which way the
 * camera is currently turned, the same way a person can walk forward while
 * looking back over their shoulder.
 */
export function stepYaw(
    currentYawDegrees: number,
    dragDeltaXPixels: number,
    sensitivity: number = YAW_DRAG_SENSITIVITY,
): number {
    return normalizeYaw(currentYawDegrees + dragDeltaXPixels * sensitivity);
}

export function rowWorldX(
    rowIndex: number,
    spacing: number = ROW_SPACING,
): number {
    return rowIndex * spacing;
}

export function cellWorldZ(
    cellNumber: number,
    spacing: number = CELL_SPACING,
): number {
    return cellNumber * spacing;
}

export function flatWorldY(
    flatNumber: number,
    spacing: number = FLAT_SPACING,
): number {
    return flatNumber * spacing;
}

export function nearestRowIndex(
    worldX: number,
    spacing: number = ROW_SPACING,
): number {
    return Math.round(worldX / spacing);
}

export function nearestCellNumber(
    worldZ: number,
    spacing: number = CELL_SPACING,
): number {
    return Math.max(1, Math.round(worldZ / spacing));
}

export function nearestFlatNumber(
    worldY: number,
    spacing: number = FLAT_SPACING,
): number {
    return Math.max(1, Math.round(worldY / spacing));
}

export interface LookDirection {
    x: number;
    y: number;
    z: number;
}

/**
 * The (unit-ish) direction the camera looks, given `pitchDegrees` (up/down)
 * and `yawDegrees` (left/right, wrapping all the way around to backward).
 * Yaw 0 faces +Z; turning right (increasing yaw) rotates toward -X, matching
 * this app's established "screen-right is world -X" handedness (see
 * `stepPosition`'s doc comment for the derivation) — so dragging right
 * (`stepYaw`) correctly turns the view toward what was previously on-screen
 * to the right.
 */
export function lookDirection(
    pitchDegrees: number,
    yawDegrees: number,
): LookDirection {
    const pitchRadians = (pitchDegrees * Math.PI) / 180;
    const yawRadians = (yawDegrees * Math.PI) / 180;

    return {
        x: -Math.sin(yawRadians) * Math.cos(pitchRadians),
        y: Math.sin(pitchRadians),
        z: Math.cos(yawRadians) * Math.cos(pitchRadians),
    };
}

export interface FacedGridCoordinate {
    rowIndex: number;
    cellNumber: number;
    flatNumber: number;
}

/**
 * The grid coordinate the camera is currently "facing" — a point
 * `probeDistance` world units ahead along the current look direction
 * (`lookDirection`), rounded to the nearest row/cell/flat. Defaults
 * `probeDistance` to one cell-spacing so it lines up with where `focusCell`
 * stands relative to the cell it targets — walking up to a cell and looking
 * at it "faces" that same cell.
 */
export function facedGridCoordinate(
    position: WalkerPosition,
    pitchDegrees: number,
    probeDistance: number = CELL_SPACING,
    yawDegrees: number = 0,
): FacedGridCoordinate {
    const direction = lookDirection(pitchDegrees, yawDegrees);

    return {
        rowIndex: nearestRowIndex(position.x + direction.x * probeDistance),
        cellNumber: nearestCellNumber(position.z + direction.z * probeDistance),
        flatNumber: nearestFlatNumber(position.y + direction.y * probeDistance),
    };
}

/**
 * The pitch (degrees) that looks straight at a box `boxWorldY` units up from
 * the ground, standing `distance` world units away from it along Z — used
 * to auto-aim the camera at a flat's shelf level when focusing a cell.
 */
export function pitchToLookAt(boxWorldY: number, distanceZ: number): number {
    if (distanceZ === 0) {
        return boxWorldY > EYE_HEIGHT ? PITCH_MAX_DEGREES : PITCH_MIN_DEGREES;
    }

    const radians = Math.atan2(boxWorldY - EYE_HEIGHT, Math.abs(distanceZ));

    return clamp(
        radians * (180 / Math.PI),
        PITCH_MIN_DEGREES,
        PITCH_MAX_DEGREES,
    );
}

/**
 * The walkable/flyable bounds for a warehouse with `rowCount` rows, cell
 * numbers up to `maxCellsCount`, and flat numbers up to `maxFlatNumber` — Y
 * ranges from just above the floor to a bit above the topmost flat's shelf
 * level, so the camera can fly up close to it without floating away.
 */
export function boundsForWarehouse(
    rowCount: number,
    maxCellsCount: number,
    maxFlatNumber: number,
): WalkerBounds {
    return {
        minX: -CELL_SPACING,
        maxX: rowWorldX(Math.max(rowCount - 1, 0)) + CELL_SPACING,
        minY: MIN_EYE_HEIGHT,
        maxY: flatWorldY(Math.max(maxFlatNumber, 1)) + FLAT_SPACING,
        minZ: 0,
        maxZ: cellWorldZ(Math.max(maxCellsCount, 1)) + CELL_SPACING,
    };
}
