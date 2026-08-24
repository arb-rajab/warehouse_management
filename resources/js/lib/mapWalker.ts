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

/**
 * Scale-safe max/min over a plain number array. `Math.max(fallback, ...numbers)`
 * spreads the whole array as call arguments, which blows the engine's
 * argument-count limit once a row/warehouse has tens of thousands of cells;
 * a loop has no such limit. `fallback` doubles as both the empty-array
 * default and a floor, matching `Math.max(fallback, ...numbers)`'s behavior.
 */
export function maxOf(numbers: number[], fallback: number = -Infinity): number {
    let max = fallback;

    for (const value of numbers) {
        if (value > max) {
            max = value;
        }
    }

    return max;
}

export function minOf(numbers: number[], fallback: number = Infinity): number {
    let min = fallback;

    for (const value of numbers) {
        if (value < min) {
            min = value;
        }
    }

    return min;
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

/**
 * Overview/orbit camera math — a second camera mode alongside the
 * first-person walker above, for seeing the whole warehouse (or a wide
 * chunk of it) at once instead of walking it row by row. The camera orbits
 * a fixed center point at a variable distance; unlike the walker it never
 * moves via WASD, only via drag (orbit) and wheel/pinch (zoom).
 */

export const ORBIT_PITCH_MIN_DEGREES = 5;
export const ORBIT_PITCH_MAX_DEGREES = 85;
export const ORBIT_DEFAULT_YAW_DEGREES = 45;
export const ORBIT_DEFAULT_PITCH_DEGREES = 35;
export const ORBIT_MIN_DISTANCE = CELL_SPACING * 2;
const ORBIT_WHEEL_ZOOM_SENSITIVITY = 0.001;

export interface OrbitState {
    yawDegrees: number;
    pitchDegrees: number;
    distance: number;
}

/**
 * The fixed point the orbit camera looks at (the warehouse's own center) and
 * the distance range it can zoom across, derived from the same bounds the
 * walker is clamped to. `defaultDistance` is a heuristic "fits the whole
 * warehouse" distance (the bounding box's diagonal), not an exact
 * field-of-view fit — good enough for an overview, not a tight frame.
 */
export interface OrbitRange {
    center: WalkerPosition;
    minDistance: number;
    maxDistance: number;
    defaultDistance: number;
}

export function orbitRangeForBounds(bounds: WalkerBounds): OrbitRange {
    const center = {
        x: (bounds.minX + bounds.maxX) / 2,
        y: (bounds.minY + bounds.maxY) / 2,
        z: (bounds.minZ + bounds.maxZ) / 2,
    };
    const diagonal = Math.hypot(
        bounds.maxX - bounds.minX,
        bounds.maxY - bounds.minY,
        bounds.maxZ - bounds.minZ,
    );
    const defaultDistance = Math.max(ORBIT_MIN_DISTANCE, diagonal);

    return {
        center,
        minDistance: ORBIT_MIN_DISTANCE,
        maxDistance: defaultDistance * 2.5,
        defaultDistance,
    };
}

/** The orbit's starting angle/distance whenever overview mode is (re)entered. */
export function defaultOrbitState(range: OrbitRange): OrbitState {
    return {
        yawDegrees: ORBIT_DEFAULT_YAW_DEGREES,
        pitchDegrees: ORBIT_DEFAULT_PITCH_DEGREES,
        distance: range.defaultDistance,
    };
}

/**
 * Advances orbit pitch by a vertical drag delta, same convention as
 * `stepPitch` (drag up looks/orbits up) but clamped to
 * [ORBIT_PITCH_MIN_DEGREES, ORBIT_PITCH_MAX_DEGREES] instead of the walker's
 * range — orbit pitch never reaches the poles (straight down/up), which
 * would otherwise make yaw ill-defined and the view flip disorientingly.
 */
export function stepOrbitPitch(
    currentPitchDegrees: number,
    dragDeltaYPixels: number,
    sensitivity: number = PITCH_DRAG_SENSITIVITY,
): number {
    return clamp(
        currentPitchDegrees - dragDeltaYPixels * sensitivity,
        ORBIT_PITCH_MIN_DEGREES,
        ORBIT_PITCH_MAX_DEGREES,
    );
}

/** Orbit yaw wraps freely all the way around, so it reuses `stepYaw` as-is. */

const ORBIT_KEY_YAW_SPEED_DEGREES = 60;
const ORBIT_KEY_PITCH_SPEED_DEGREES = 60;
/** Exponential zoom rate per second for held-key zoom — always yields a positive distance, unlike an additive step. */
const ORBIT_KEY_ZOOM_RATE_PER_SECOND = 1.5;

/**
 * Drives orbit yaw/pitch/distance from held keys instead of a pointer drag —
 * the same `pressedDirections` set/keys `stepPosition` (walk mode) reads, so
 * WASD/arrows and Space/Shift double as orbit controls without new bindings.
 * forward/backward tilt pitch up/down, left/right yaw, up/down (Space/Shift)
 * zoom in/out. Zoom is multiplicative (`Math.exp`), matching
 * `stepOrbitDistance`'s multiplicative wheel-zoom style and always staying
 * positive regardless of `deltaSeconds`.
 */
export function stepOrbitStateByKeys(
    orbit: OrbitState,
    pressedDirections: ReadonlySet<MoveDirection>,
    deltaSeconds: number,
    range: OrbitRange,
): OrbitState {
    let { yawDegrees, pitchDegrees, distance } = orbit;

    if (pressedDirections.has('left')) {
        yawDegrees -= ORBIT_KEY_YAW_SPEED_DEGREES * deltaSeconds;
    }

    if (pressedDirections.has('right')) {
        yawDegrees += ORBIT_KEY_YAW_SPEED_DEGREES * deltaSeconds;
    }

    if (pressedDirections.has('forward')) {
        pitchDegrees += ORBIT_KEY_PITCH_SPEED_DEGREES * deltaSeconds;
    }

    if (pressedDirections.has('backward')) {
        pitchDegrees -= ORBIT_KEY_PITCH_SPEED_DEGREES * deltaSeconds;
    }

    if (pressedDirections.has('up')) {
        distance *= Math.exp(-ORBIT_KEY_ZOOM_RATE_PER_SECOND * deltaSeconds);
    }

    if (pressedDirections.has('down')) {
        distance *= Math.exp(ORBIT_KEY_ZOOM_RATE_PER_SECOND * deltaSeconds);
    }

    return {
        yawDegrees: normalizeYaw(yawDegrees),
        pitchDegrees: clamp(
            pitchDegrees,
            ORBIT_PITCH_MIN_DEGREES,
            ORBIT_PITCH_MAX_DEGREES,
        ),
        distance: clamp(distance, range.minDistance, range.maxDistance),
    };
}

/** Zooms by mouse wheel — positive `wheelDeltaY` (scrolling down) zooms out. */
export function stepOrbitDistance(
    currentDistance: number,
    wheelDeltaY: number,
    range: OrbitRange,
    sensitivity: number = ORBIT_WHEEL_ZOOM_SENSITIVITY,
): number {
    return clamp(
        currentDistance * (1 + wheelDeltaY * sensitivity),
        range.minDistance,
        range.maxDistance,
    );
}

/**
 * Zooms by two-finger pinch, mirroring `zoomFromPinch` in mapViewport.ts:
 * fingers spreading apart (a bigger current gap than the pinch started with)
 * zooms in (a smaller distance), so the ratio is inverted relative to the 2D
 * viewport's zoom scalar (which grows, not shrinks, as the pinch widens).
 */
export function orbitDistanceFromPinch(
    distanceAtPinchStart: number,
    startPointerGap: number,
    currentPointerGap: number,
    range: OrbitRange,
): number {
    if (startPointerGap === 0) {
        return clamp(
            distanceAtPinchStart,
            range.minDistance,
            range.maxDistance,
        );
    }

    return clamp(
        distanceAtPinchStart * (startPointerGap / currentPointerGap),
        range.minDistance,
        range.maxDistance,
    );
}

/** The orbit camera's world position, given where it's centered and its current angle/distance. */
export function orbitCameraPosition(
    range: OrbitRange,
    orbit: OrbitState,
): WalkerPosition {
    const yawRadians = (orbit.yawDegrees * Math.PI) / 180;
    const pitchRadians = (orbit.pitchDegrees * Math.PI) / 180;
    const horizontalDistance = orbit.distance * Math.cos(pitchRadians);

    return {
        x: range.center.x + horizontalDistance * Math.sin(yawRadians),
        y: range.center.y + orbit.distance * Math.sin(pitchRadians),
        z: range.center.z + horizontalDistance * Math.cos(yawRadians),
    };
}
