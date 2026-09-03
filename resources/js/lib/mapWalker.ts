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
import { normalizeDegrees } from '@/lib/geometry';

export const ROW_SPACING = 4;
export const CELL_SPACING = 3;
export const FLAT_SPACING = 2.5;
export const EYE_HEIGHT = 1.7;
export const MIN_EYE_HEIGHT = 0.5;

/** Side length of a cell's shelf box (CellMap3D.vue's render geometry) — also the collision volume's base size, so a cell blocks walking exactly where it visually sits. */
export const BOX_SIZE = 1.4;
/** Extra clearance added around a cell's box for collision, so the camera stops at the box's edge rather than clipping into a corner of it. */
export const PLAYER_COLLISION_RADIUS = 0.25;
/**
 * Half-extent of a cell's collision volume along each axis. Must stay well
 * under half of the smallest grid spacing (FLAT_SPACING / 2 = 1.25) so a
 * candidate position can only ever be within range of a single grid point
 * per axis — `collidesWithOccupiedCell` relies on that to do an O(1)
 * nearest-cell lookup instead of scanning every occupied cell.
 */
const CELL_COLLISION_HALF_EXTENT = BOX_SIZE / 2 + PLAYER_COLLISION_RADIUS;

export const WALK_SPEED = 4;
/** Multiplier applied to WALK_SPEED while sprinting (Ctrl held on desktop, toggled on touch). */
export const SPRINT_MULTIPLIER = 2.5;

/** Vertical field of view of the walk/orbit `THREE.PerspectiveCamera` — shared with `worldPointToScreenPercent` so the row-label projection matches what the real camera actually shows. */
export const CAMERA_FOV_DEGREES = 70;

export const PITCH_MIN_DEGREES = -75;
export const PITCH_MAX_DEGREES = 75;
const PITCH_DRAG_SENSITIVITY = 0.15;
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

/** Shared empty occupancy set for callers that don't pass one — avoids allocating a fresh `Set` per call when `stepPosition` is invoked without collision (e.g. existing tests, orbit-mode-only code paths). */
const NO_OCCUPIED_CELLS: CellOccupancyLookup = new Set<string>();

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
 *
 * `occupiedCellKeys` (optional — omitting it disables collision entirely,
 * e.g. for orbit mode or tests that don't care) blocks the camera from
 * entering an occupied cell's shelf box (`collidingCellKey`), one axis at a
 * time: each of Z, then X, then Y is only applied if the resulting position
 * — combined with whichever of the *other* axes has already been resolved
 * this call — doesn't newly collide, so a step that's blocked on one axis
 * still lets you slide along an unblocked one (e.g. holding forward+left
 * with a box directly ahead still lets you strafe left) instead of stopping
 * dead the instant any single axis is blocked. "Newly" matters: if the
 * camera already started this step inside an occupied cell's box (e.g.
 * `focusCell` deliberately stands you exactly where the previous cell's box
 * is), movement that stays within *that same* cell's collision volume is
 * still allowed, so a spawn-in-box position can be walked back out of
 * instead of trapping the camera there permanently.
 */
export function stepPosition(
    position: WalkerPosition,
    pressedDirections: ReadonlySet<MoveDirection>,
    deltaSeconds: number,
    bounds: WalkerBounds,
    speed: number = WALK_SPEED,
    occupiedCellKeys: CellOccupancyLookup = NO_OCCUPIED_CELLS,
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

    let resolvedX = position.x;
    let resolvedY = position.y;
    let resolvedZ = position.z;

    // `focusCell` (and search/jump navigation built on it) deliberately
    // stands the camera exactly where the previous cell's box sits, so the
    // camera can legitimately start a step already overlapping an occupied
    // cell — e.g. right after jumping to it. Only block a step that would
    // enter a *different* occupied cell than the one (if any) the camera is
    // already standing in, so a spawn-in-box position can still be walked
    // out of instead of trapping the camera forever.
    const startCollisionKey = collidingCellKey(position, occupiedCellKeys);

    function entersNewOccupiedCell(candidate: WalkerPosition): boolean {
        const hitKey = collidingCellKey(candidate, occupiedCellKeys);

        return hitKey !== null && hitKey !== startCollisionKey;
    }

    if (
        z !== position.z &&
        !entersNewOccupiedCell({ x: resolvedX, y: resolvedY, z })
    ) {
        resolvedZ = z;
    }

    if (
        x !== position.x &&
        !entersNewOccupiedCell({ x, y: resolvedY, z: resolvedZ })
    ) {
        resolvedX = x;
    }

    if (
        y !== position.y &&
        !entersNewOccupiedCell({ x: resolvedX, y, z: resolvedZ })
    ) {
        resolvedY = y;
    }

    return {
        x: clamp(resolvedX, bounds.minX, bounds.maxX),
        y: clamp(resolvedY, bounds.minY, bounds.maxY),
        z: clamp(resolvedZ, bounds.minZ, bounds.maxZ),
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

export const normalizeYaw = normalizeDegrees;

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

/** The lookup key for a grid cell — shared by CellMap3D.vue's `cellLookup`/`instanceSlotByKey` and the collision check below, so a cell only ever needs to be keyed one way. */
export function facedKey(
    rowIndex: number,
    cellNumber: number,
    flatNumber: number,
): string {
    return `${rowIndex}:${cellNumber}:${flatNumber}`;
}

/** Anything that can answer "is this cell key occupied?" in O(1) — both `Set<string>` and CellMap3D.vue's `instanceSlotByKey` (a `Map`) satisfy this structurally, so the caller doesn't need to allocate a fresh Set every frame just to pass one in. */
export interface CellOccupancyLookup {
    has(key: string): boolean;
}

/**
 * The occupied cell key `position` sits inside (or within
 * `PLAYER_COLLISION_RADIUS` of), or `null` if it's in the clear. Only checks
 * the single nearest grid coordinate per axis (not every occupied cell) —
 * safe because `CELL_COLLISION_HALF_EXTENT` is kept well under half the
 * smallest grid spacing, so a position can only ever be in range of one grid
 * point per axis at a time.
 */
export function collidingCellKey(
    position: WalkerPosition,
    occupiedKeys: CellOccupancyLookup,
): string | null {
    const rowIndex = nearestRowIndex(position.x);
    const cellNumber = nearestCellNumber(position.z);
    const flatNumber = nearestFlatNumber(position.y);
    const key = facedKey(rowIndex, cellNumber, flatNumber);

    if (!occupiedKeys.has(key)) {
        return null;
    }

    const colliding =
        Math.abs(position.x - rowWorldX(rowIndex)) <
            CELL_COLLISION_HALF_EXTENT &&
        Math.abs(position.y - flatWorldY(flatNumber)) <
            CELL_COLLISION_HALF_EXTENT &&
        Math.abs(position.z - cellWorldZ(cellNumber)) <
            CELL_COLLISION_HALF_EXTENT;

    return colliding ? key : null;
}

/** Whether `position` collides with any occupied cell's shelf box — see `collidingCellKey`. */
export function collidesWithOccupiedCell(
    position: WalkerPosition,
    occupiedKeys: CellOccupancyLookup,
): boolean {
    return collidingCellKey(position, occupiedKeys) !== null;
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

export interface ScreenPercentPosition {
    leftPercent: number;
    topPercent: number;
    /** False once the point is behind the camera or far enough outside the frame that it shouldn't be drawn. */
    visible: boolean;
}

/**
 * Projects a world point onto the walk camera's screen, as a left%/top%
 * position over the viewport — used to draw an HTML-overlay label (row
 * signage) at a 3D position without touching three.js/WebGL, so this stays
 * unit-testable like the rest of this module. Reimplements the same
 * right-handed lookAt basis `stepPosition`'s doc comment derives (`right = up
 * × back`) rather than depending on the real camera object, then a standard
 * symmetric-frustum perspective divide using the camera's vertical FOV and
 * the viewport's aspect ratio.
 */
export function worldPointToScreenPercent(
    position: WalkerPosition,
    pitchDegrees: number,
    yawDegrees: number,
    aspect: number,
    point: WalkerPosition,
    fovYDegrees: number = CAMERA_FOV_DEGREES,
): ScreenPercentPosition {
    const forward = lookDirection(pitchDegrees, yawDegrees);
    const back = { x: -forward.x, y: -forward.y, z: -forward.z };
    const worldUp = { x: 0, y: 1, z: 0 };
    const right = normalize(cross(worldUp, back));
    const up = cross(back, right);

    const relative = {
        x: point.x - position.x,
        y: point.y - position.y,
        z: point.z - position.z,
    };

    const viewX = dot(relative, right);
    const viewY = dot(relative, up);
    const depthInFront = -dot(relative, back);

    if (depthInFront <= 0.001) {
        return { leftPercent: 50, topPercent: 50, visible: false };
    }

    const tanHalfFovY = Math.tan((fovYDegrees * Math.PI) / 180 / 2);
    const ndcX = viewX / (depthInFront * tanHalfFovY * aspect);
    const ndcY = viewY / (depthInFront * tanHalfFovY);

    return {
        leftPercent: ((ndcX + 1) / 2) * 100,
        topPercent: ((1 - ndcY) / 2) * 100,
        visible: Math.abs(ndcX) <= 1.2 && Math.abs(ndcY) <= 1.2,
    };
}

interface Vector3Like {
    x: number;
    y: number;
    z: number;
}

function cross(a: Vector3Like, b: Vector3Like): Vector3Like {
    return {
        x: a.y * b.z - a.z * b.y,
        y: a.z * b.x - a.x * b.z,
        z: a.x * b.y - a.y * b.x,
    };
}

function dot(a: Vector3Like, b: Vector3Like): number {
    return a.x * b.x + a.y * b.y + a.z * b.z;
}

function normalize(v: Vector3Like): Vector3Like {
    const length = Math.hypot(v.x, v.y, v.z) || 1;

    return { x: v.x / length, y: v.y / length, z: v.z / length };
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

/**
 * Top-down mini-map math for the walk-mode orientation aid (CellMap3D.vue) —
 * a small overlay showing the warehouse's row/aisle layout, the player's
 * position, and which way they're facing, since walking a large warehouse
 * row-by-row with only a text "standing near" label gives no sense of where
 * you are relative to the whole building the way orbit mode's overview does.
 *
 * Screen convention: X (row axis) maps to left% *inverted* (increasing X =
 * further LEFT); Z (cell-number/depth axis) maps to top% inverted the same
 * way (increasing Z = smaller top%, i.e. "up") so that facing yaw 0 — the
 * default look direction, +Z — reads as "facing up" on the mini-map, matching
 * the everyday convention of an up-pointing arrow meaning "forward". X is
 * mirrored (not mapped directly) because the walk camera's own screen-right
 * is world -X (see `stepPosition`'s "Handedness trap" doc comment) — mapping
 * +X straight to "further right" here would put the mini-map's left/right
 * backwards relative to what the player actually sees while walking (e.g.
 * strafing right, which decreases world X, would move the marker toward the
 * mini-map's left instead of its right).
 */
export function miniMapPercentX(worldX: number, bounds: WalkerBounds): number {
    const width = bounds.maxX - bounds.minX || 1;

    return clamp(100 - ((worldX - bounds.minX) / width) * 100, 0, 100);
}

export function miniMapPercentZ(worldZ: number, bounds: WalkerBounds): number {
    const depth = bounds.maxZ - bounds.minZ || 1;

    return clamp(100 - ((worldZ - bounds.minZ) / depth) * 100, 0, 100);
}

export interface MiniMapPosition {
    leftPercent: number;
    topPercent: number;
}

export function miniMapPosition(
    position: WalkerPosition,
    bounds: WalkerBounds,
): MiniMapPosition {
    return {
        leftPercent: miniMapPercentX(position.x, bounds),
        topPercent: miniMapPercentZ(position.z, bounds),
    };
}

/** One left% per row (its `rowWorldX`), for drawing an aisle line per row on the mini-map. */
export function miniMapRowLeftPercents(
    rowCount: number,
    bounds: WalkerBounds,
): number[] {
    return Array.from({ length: rowCount }, (_, rowIndex) =>
        miniMapPercentX(rowWorldX(rowIndex), bounds),
    );
}

/**
 * The CSS rotation (degrees) for a default-"pointing up" heading arrow, given
 * the walker's yaw. Derived from `lookDirection`'s (x, z) = (-sin(yaw),
 * cos(yaw)): at yaw 0 the direction is +Z, which under this module's
 * top-down screen convention (above) is "up" — matching the arrow's
 * default orientation, so no rotation is needed (0deg). Turning right
 * (increasing yaw) swings the look direction toward -X, which is now the
 * mini-map's RIGHT side (`miniMapPercentX` mirrors X — see its doc comment);
 * CSS `rotate()` is clockwise-positive, so pointing the arrow toward the
 * mini-map's right on a right turn just needs +yaw directly, not its
 * negation.
 */
export function miniMapHeadingDegrees(yawDegrees: number): number {
    return normalizeYaw(yawDegrees);
}
