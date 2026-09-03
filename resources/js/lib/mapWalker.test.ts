import { describe, expect, it } from 'vitest';
import {
    BOX_SIZE,
    boundsForWarehouse,
    CAMERA_FOV_DEGREES,
    CELL_SPACING,
    cellWorldZ,
    clamp,
    collidesWithOccupiedCell,
    defaultOrbitState,
    EYE_HEIGHT,
    facedGridCoordinate,
    facedKey,
    FLAT_SPACING,
    flatWorldY,
    lookDirection,
    maxOf,
    MIN_EYE_HEIGHT,
    miniMapHeadingDegrees,
    miniMapPercentX,
    miniMapPercentZ,
    miniMapPosition,
    miniMapRowLeftPercents,
    minOf,
    moveDirectionForKey,
    nearestCellNumber,
    nearestFlatNumber,
    nearestRowIndex,
    normalizeYaw,
    orbitCameraPosition,
    orbitDistanceFromPinch,
    ORBIT_DEFAULT_PITCH_DEGREES,
    ORBIT_DEFAULT_YAW_DEGREES,
    ORBIT_MIN_DISTANCE,
    ORBIT_PITCH_MAX_DEGREES,
    ORBIT_PITCH_MIN_DEGREES,
    orbitRangeForBounds,
    PITCH_MAX_DEGREES,
    PITCH_MIN_DEGREES,
    pitchToLookAt,
    ROW_SPACING,
    rowWorldX,
    SPRINT_MULTIPLIER,
    stepOrbitDistance,
    stepOrbitPitch,
    stepOrbitStateByKeys,
    stepPitch,
    stepPosition,
    stepYaw,
    worldPointToScreenPercent,
} from './mapWalker';

describe('moveDirectionForKey', () => {
    it('maps WASD and arrow keys to their movement direction, case-insensitively', () => {
        expect(moveDirectionForKey('w')).toBe('forward');
        expect(moveDirectionForKey('W')).toBe('forward');
        expect(moveDirectionForKey('ArrowUp')).toBe('forward');
        expect(moveDirectionForKey('s')).toBe('backward');
        expect(moveDirectionForKey('ArrowDown')).toBe('backward');
        expect(moveDirectionForKey('a')).toBe('left');
        expect(moveDirectionForKey('ArrowLeft')).toBe('left');
        expect(moveDirectionForKey('d')).toBe('right');
        expect(moveDirectionForKey('ArrowRight')).toBe('right');
    });

    it('maps Space to up and Shift to down, to fly between flats', () => {
        expect(moveDirectionForKey(' ')).toBe('up');
        expect(moveDirectionForKey('Shift')).toBe('down');
    });

    it('returns null for keys that do not drive movement', () => {
        expect(moveDirectionForKey('Enter')).toBeNull();
        expect(moveDirectionForKey('q')).toBeNull();
    });
});

describe('clamp', () => {
    it('clamps a value into the given range', () => {
        expect(clamp(5, 0, 10)).toBe(5);
        expect(clamp(-5, 0, 10)).toBe(0);
        expect(clamp(15, 0, 10)).toBe(10);
    });
});

describe('maxOf / minOf', () => {
    it('finds the max/min of a plain array, matching Math.max/Math.min', () => {
        expect(maxOf([3, 1, 4, 1, 5, 9])).toBe(9);
        expect(minOf([3, 1, 4, 1, 5, 9])).toBe(1);
    });

    it('applies the fallback as a floor/ceiling even when the array is non-empty', () => {
        expect(maxOf([1, 2, 3], 10)).toBe(10);
        expect(minOf([1, 2, 3], -10)).toBe(-10);
    });

    it('returns the fallback for an empty array', () => {
        expect(maxOf([], 1)).toBe(1);
        expect(minOf([], 1)).toBe(1);
    });

    it('handles arrays too large to spread as call arguments without throwing', () => {
        const huge = Array.from({ length: 200_000 }, (_, i) => i + 1);

        expect(maxOf(huge, 1)).toBe(200_000);
        expect(minOf(huge)).toBe(1);
    });
});

describe('stepPosition', () => {
    const bounds = { minX: -5, maxX: 5, minY: 0, maxY: 10, minZ: 0, maxZ: 10 };

    it('moves forward along Z, and "right" toward screen-right (world -X)', () => {
        const next = stepPosition(
            { x: 0, y: 1, z: 0 },
            new Set(['forward', 'right']),
            1,
            bounds,
            4,
        );

        expect(next).toEqual({ x: -4, y: 1, z: 4 });
    });

    it('moves backward along Z, and "left" toward screen-left (world +X)', () => {
        const next = stepPosition(
            { x: 0, y: 1, z: 5 },
            new Set(['backward', 'left']),
            1,
            bounds,
            4,
        );

        expect(next).toEqual({ x: 4, y: 1, z: 1 });
    });

    it('flies up and down along Y', () => {
        const up = stepPosition(
            { x: 0, y: 1, z: 0 },
            new Set(['up']),
            1,
            bounds,
            4,
        );
        expect(up).toEqual({ x: 0, y: 5, z: 0 });

        const down = stepPosition(
            { x: 0, y: 5, z: 0 },
            new Set(['down']),
            1,
            bounds,
            4,
        );
        expect(down).toEqual({ x: 0, y: 1, z: 0 });
    });

    it('cancels out opposite directions held at once', () => {
        const next = stepPosition(
            { x: 0, y: 1, z: 0 },
            new Set(['forward', 'backward', 'left', 'right', 'up', 'down']),
            1,
            bounds,
            4,
        );

        expect(next).toEqual({ x: 0, y: 1, z: 0 });
    });

    it('clamps the result to the warehouse bounds', () => {
        const next = stepPosition(
            { x: -4, y: 9, z: 9 },
            new Set(['forward', 'right', 'up']),
            1,
            bounds,
            4,
        );

        expect(next).toEqual({ x: -5, y: 10, z: 10 });
    });

    it('does not move when no direction is held', () => {
        const next = stepPosition(
            { x: 1, y: 2, z: 2 },
            new Set(),
            1,
            bounds,
            4,
        );

        expect(next).toEqual({ x: 1, y: 2, z: 2 });
    });

    describe('with collision (occupiedCellKeys)', () => {
        // Row 0, cell 2, flat 1 is "occupied" throughout this block —
        // standing at cellWorldZ(1) facing forward (+Z), the box sits
        // exactly one step ahead.
        const occupied = new Set([facedKey(0, 2, 1)]);
        const start = { x: rowWorldX(0), y: flatWorldY(1), z: cellWorldZ(1) };

        it('blocks moving into an occupied cell instead of passing through it', () => {
            const next = stepPosition(
                start,
                new Set(['forward']),
                1,
                bounds,
                CELL_SPACING,
                occupied,
            );

            expect(next).toEqual(start);
        });

        it('leaves an unoccupied aisle fully walkable', () => {
            const next = stepPosition(
                start,
                new Set(['backward']),
                1,
                bounds,
                CELL_SPACING,
                occupied,
            );

            expect(next).toEqual({ ...start, z: start.z - CELL_SPACING });
        });

        it('slides along an unblocked axis when another axis is blocked (diagonal movement)', () => {
            const next = stepPosition(
                start,
                new Set(['forward', 'left']),
                1,
                bounds,
                CELL_SPACING,
                occupied,
            );

            // Forward is blocked by the occupied cell; strafing left (world
            // +X) is unobstructed and still applies.
            expect(next.z).toBe(start.z);
            expect(next.x).toBe(start.x + CELL_SPACING);
        });

        it('does not block movement when no occupancy set is passed (backward compatible)', () => {
            const next = stepPosition(
                start,
                new Set(['forward']),
                1,
                bounds,
                CELL_SPACING,
            );

            expect(next).toEqual({ ...start, z: start.z + CELL_SPACING });
        });

        it('lets the camera move back out of a cell it already started inside (e.g. right after focusCell)', () => {
            // focusCell deliberately stands the camera exactly on top of the
            // *previous* cell's box, so a step can legitimately start already
            // overlapping an occupied cell — that must not trap it there.
            const insideOccupiedCell = {
                x: rowWorldX(0),
                y: flatWorldY(1),
                z: cellWorldZ(2),
            };
            const tinyStepBack = stepPosition(
                insideOccupiedCell,
                new Set(['backward']),
                0.05,
                bounds,
                CELL_SPACING,
                occupied,
            );

            expect(tinyStepBack.z).toBeLessThan(insideOccupiedCell.z);
        });

        it('still blocks moving from inside one occupied cell straight into a different occupied cell', () => {
            const twoOccupied = new Set([facedKey(0, 1, 1), facedKey(0, 2, 1)]);
            const insideCell1 = {
                x: rowWorldX(0),
                y: flatWorldY(1),
                z: cellWorldZ(1),
            };

            const next = stepPosition(
                insideCell1,
                new Set(['forward']),
                1,
                bounds,
                CELL_SPACING,
                twoOccupied,
            );

            expect(next).toEqual(insideCell1);
        });
    });
});

describe('collidesWithOccupiedCell', () => {
    it('is false when the nearest grid cell is not in the occupied set', () => {
        const position = {
            x: rowWorldX(0),
            y: flatWorldY(1),
            z: cellWorldZ(2),
        };

        expect(collidesWithOccupiedCell(position, new Set())).toBe(false);
    });

    it("is true exactly at an occupied cell's grid position", () => {
        const position = {
            x: rowWorldX(1),
            y: flatWorldY(2),
            z: cellWorldZ(3),
        };
        const occupied = new Set([facedKey(1, 3, 2)]);

        expect(collidesWithOccupiedCell(position, occupied)).toBe(true);
    });

    it("stops colliding once far enough from the occupied cell's box, even on the same grid coordinate's near side", () => {
        const occupied = new Set([facedKey(0, 1, 1)]);
        const halfExtent = BOX_SIZE / 2 + 0.25;

        const justInside = {
            x: rowWorldX(0),
            y: flatWorldY(1),
            z: cellWorldZ(1) + halfExtent - 0.01,
        };
        const justOutside = {
            x: rowWorldX(0),
            y: flatWorldY(1),
            z: cellWorldZ(1) + halfExtent + 0.01,
        };

        expect(collidesWithOccupiedCell(justInside, occupied)).toBe(true);
        expect(collidesWithOccupiedCell(justOutside, occupied)).toBe(false);
    });

    it('does not collide with a neighboring row/cell/flat that is not itself occupied', () => {
        const occupied = new Set([facedKey(0, 1, 1)]);
        const neighborRow = {
            x: rowWorldX(1),
            y: flatWorldY(1),
            z: cellWorldZ(1),
        };
        const neighborCell = {
            x: rowWorldX(0),
            y: flatWorldY(1),
            z: cellWorldZ(2),
        };
        const neighborFlat = {
            x: rowWorldX(0),
            y: flatWorldY(2),
            z: cellWorldZ(1),
        };

        expect(collidesWithOccupiedCell(neighborRow, occupied)).toBe(false);
        expect(collidesWithOccupiedCell(neighborCell, occupied)).toBe(false);
        expect(collidesWithOccupiedCell(neighborFlat, occupied)).toBe(false);
    });
});

describe('stepPitch', () => {
    it('looks up when dragging up (a negative screen-Y delta)', () => {
        expect(stepPitch(0, -10, 1)).toBe(10);
    });

    it('looks down when dragging down (a positive screen-Y delta)', () => {
        expect(stepPitch(0, 10, 1)).toBe(-10);
    });

    it('clamps to the pitch bounds', () => {
        expect(stepPitch(0, -1000, 1)).toBe(PITCH_MAX_DEGREES);
        expect(stepPitch(0, 1000, 1)).toBe(PITCH_MIN_DEGREES);
    });
});

describe('rowWorldX / cellWorldZ / flatWorldY', () => {
    it('spaces rows, cells, and flats evenly along their own axis', () => {
        expect(rowWorldX(0)).toBe(0);
        expect(rowWorldX(2)).toBe(2 * ROW_SPACING);
        expect(cellWorldZ(1)).toBe(CELL_SPACING);
        expect(cellWorldZ(3)).toBe(3 * CELL_SPACING);
        expect(flatWorldY(1)).toBeGreaterThan(0);
        expect(flatWorldY(2)).toBeGreaterThan(flatWorldY(1));
    });
});

describe('pitchToLookAt', () => {
    it('returns a positive pitch (looking up) for a box above eye height', () => {
        expect(pitchToLookAt(EYE_HEIGHT + 5, 3)).toBeGreaterThan(0);
    });

    it('returns a negative pitch (looking down) for a box below eye height', () => {
        expect(pitchToLookAt(0, 3)).toBeLessThan(0);
    });

    it('returns 0 pitch for a box level with eye height', () => {
        expect(pitchToLookAt(EYE_HEIGHT, 3)).toBeCloseTo(0);
    });

    it('clamps to the pitch bounds even at zero distance', () => {
        expect(pitchToLookAt(EYE_HEIGHT + 100, 0)).toBe(PITCH_MAX_DEGREES);
        expect(pitchToLookAt(-100, 0)).toBe(PITCH_MIN_DEGREES);
    });
});

describe('boundsForWarehouse', () => {
    it('spans from just before the first row/cell to just past the last, with a margin', () => {
        const bounds = boundsForWarehouse(3, 5, 4);

        expect(bounds.minX).toBe(-CELL_SPACING);
        expect(bounds.maxX).toBe(rowWorldX(2) + CELL_SPACING);
        expect(bounds.minZ).toBe(0);
        expect(bounds.maxZ).toBe(cellWorldZ(5) + CELL_SPACING);
        expect(bounds.minY).toBe(MIN_EYE_HEIGHT);
        expect(bounds.maxY).toBe(flatWorldY(4) + FLAT_SPACING);
    });

    it('handles an empty warehouse without negative spans', () => {
        const bounds = boundsForWarehouse(0, 0, 0);

        expect(bounds.maxX).toBeGreaterThanOrEqual(bounds.minX);
        expect(bounds.maxZ).toBeGreaterThanOrEqual(bounds.minZ);
        expect(bounds.maxY).toBeGreaterThan(bounds.minY);
    });
});

describe('nearestRowIndex / nearestCellNumber / nearestFlatNumber', () => {
    it('rounds a world position to the nearest grid index', () => {
        expect(nearestRowIndex(ROW_SPACING * 2.4)).toBe(2);
        expect(nearestRowIndex(ROW_SPACING * 2.6)).toBe(3);
    });

    it('clamps cell number and flat number to a minimum of 1', () => {
        expect(nearestCellNumber(-100)).toBe(1);
        expect(nearestFlatNumber(-100)).toBe(1);
    });
});

describe('facedGridCoordinate', () => {
    it('faces straight ahead at the current eye height when pitch is 0', () => {
        const faced = facedGridCoordinate(
            { x: rowWorldX(2), y: EYE_HEIGHT, z: 0 },
            0,
        );

        expect(faced.rowIndex).toBe(2);
        expect(faced.cellNumber).toBe(nearestCellNumber(CELL_SPACING));
        expect(faced.flatNumber).toBe(nearestFlatNumber(EYE_HEIGHT));
    });

    it('faces a higher flat when pitched up', () => {
        const position = { x: 0, y: EYE_HEIGHT, z: 0 };
        const level = facedGridCoordinate(position, 0);
        const pitchedUp = facedGridCoordinate(position, 45);

        expect(pitchedUp.flatNumber).toBeGreaterThan(level.flatNumber);
    });

    it('faces a lower flat when pitched down', () => {
        const position = { x: 0, y: EYE_HEIGHT, z: 0 };
        const level = facedGridCoordinate(position, 0);
        const pitchedDown = facedGridCoordinate(position, -45);

        expect(pitchedDown.flatNumber).toBeLessThanOrEqual(level.flatNumber);
    });

    it('faces a higher flat when the camera itself has flown higher, even at 0 pitch', () => {
        const low = facedGridCoordinate({ x: 0, y: EYE_HEIGHT, z: 0 }, 0);
        const high = facedGridCoordinate(
            { x: 0, y: EYE_HEIGHT + flatWorldY(3), z: 0 },
            0,
        );

        expect(high.flatNumber).toBeGreaterThan(low.flatNumber);
    });

    it("matches focusCell's standing distance by default (one cell-spacing ahead)", () => {
        // focusCell stands one CELL_SPACING behind its target cell and looks
        // forward — facedGridCoordinate's default probe distance must land
        // back on that same cell for the "faced cell" panel to show it.
        const targetCellNumber = 4;
        const standingZ = cellWorldZ(targetCellNumber) - CELL_SPACING;

        const faced = facedGridCoordinate(
            { x: 0, y: EYE_HEIGHT, z: standingZ },
            0,
        );

        expect(faced.cellNumber).toBe(targetCellNumber);
    });

    it('faces a lower cell number when yawed 180° (looking backward)', () => {
        const position = { x: 0, y: EYE_HEIGHT, z: cellWorldZ(10) };

        const forward = facedGridCoordinate(position, 0, CELL_SPACING, 0);
        const backward = facedGridCoordinate(position, 0, CELL_SPACING, 180);

        expect(forward.cellNumber).toBeGreaterThan(10);
        expect(backward.cellNumber).toBeLessThan(10);
    });

    it('faces a different row when yawed 90°, at the same X the camera stands at', () => {
        const position = { x: rowWorldX(2), y: EYE_HEIGHT, z: 0 };

        const straightAhead = facedGridCoordinate(position, 0, CELL_SPACING, 0);
        const turnedRight = facedGridCoordinate(position, 0, CELL_SPACING, 90);

        expect(straightAhead.rowIndex).toBe(2);
        expect(turnedRight.rowIndex).not.toBe(2);
    });
});

describe('worldPointToScreenPercent', () => {
    it('centers a point straight ahead at eye height', () => {
        const result = worldPointToScreenPercent(
            { x: 0, y: EYE_HEIGHT, z: 0 },
            0,
            0,
            1,
            { x: 0, y: EYE_HEIGHT, z: 10 },
        );

        expect(result.visible).toBe(true);
        expect(result.leftPercent).toBeCloseTo(50);
        expect(result.topPercent).toBeCloseTo(50);
    });

    it('places a point to the world-left of straight-ahead on the left half of the screen', () => {
        // Screen-left is world +X (see stepPosition's handedness note).
        const result = worldPointToScreenPercent(
            { x: 0, y: EYE_HEIGHT, z: 0 },
            0,
            0,
            1,
            { x: 5, y: EYE_HEIGHT, z: 10 },
        );

        expect(result.leftPercent).toBeLessThan(50);
    });

    it('places a point above eye height on the top half of the screen', () => {
        const result = worldPointToScreenPercent(
            { x: 0, y: EYE_HEIGHT, z: 0 },
            0,
            0,
            1,
            { x: 0, y: EYE_HEIGHT + 5, z: 10 },
        );

        expect(result.topPercent).toBeLessThan(50);
    });

    it('is not visible for a point behind the camera', () => {
        const result = worldPointToScreenPercent(
            { x: 0, y: EYE_HEIGHT, z: 0 },
            0,
            0,
            1,
            { x: 0, y: EYE_HEIGHT, z: -10 },
        );

        expect(result.visible).toBe(false);
    });

    it('follows yaw — a point behind becomes visible once turned to face it', () => {
        const position = { x: 0, y: EYE_HEIGHT, z: 0 };
        const point = { x: 0, y: EYE_HEIGHT, z: -10 };

        expect(
            worldPointToScreenPercent(position, 0, 0, 1, point).visible,
        ).toBe(false);
        expect(
            worldPointToScreenPercent(position, 0, 180, 1, point).visible,
        ).toBe(true);
    });

    it('defaults the vertical FOV to CAMERA_FOV_DEGREES', () => {
        const withDefault = worldPointToScreenPercent(
            { x: 0, y: EYE_HEIGHT, z: 0 },
            0,
            0,
            1,
            { x: 3, y: EYE_HEIGHT, z: 10 },
        );
        const withExplicit = worldPointToScreenPercent(
            { x: 0, y: EYE_HEIGHT, z: 0 },
            0,
            0,
            1,
            { x: 3, y: EYE_HEIGHT, z: 10 },
            CAMERA_FOV_DEGREES,
        );

        expect(withDefault).toEqual(withExplicit);
    });
});

describe('normalizeYaw', () => {
    it('wraps into [0, 360)', () => {
        expect(normalizeYaw(-10)).toBe(350);
        expect(normalizeYaw(370)).toBe(10);
        expect(normalizeYaw(0)).toBe(0);
    });
});

describe('stepYaw', () => {
    it('turns right (increases yaw) when dragging right (a positive screen-X delta)', () => {
        expect(stepYaw(0, 10, 1)).toBe(10);
    });

    it('turns left (decreases/wraps yaw) when dragging left (a negative screen-X delta)', () => {
        expect(stepYaw(0, -10, 1)).toBe(350);
    });

    it('wraps all the way around instead of clamping', () => {
        expect(stepYaw(350, 20, 1)).toBe(10);
    });
});

describe('lookDirection', () => {
    it('faces +Z with no pitch/yaw', () => {
        const direction = lookDirection(0, 0);

        expect(direction.x).toBeCloseTo(0);
        expect(direction.y).toBeCloseTo(0);
        expect(direction.z).toBeCloseTo(1);
    });

    it('faces -Z when yawed 180° (backward)', () => {
        const direction = lookDirection(0, 180);

        expect(direction.x).toBeCloseTo(0);
        expect(direction.z).toBeCloseTo(-1);
    });

    it('faces toward -X when yawed 90° (turned right)', () => {
        const direction = lookDirection(0, 90);

        expect(direction.x).toBeCloseTo(-1);
        expect(direction.z).toBeCloseTo(0);
    });

    it('adds a positive Y component when pitched up', () => {
        expect(lookDirection(45, 0).y).toBeGreaterThan(0);
        expect(lookDirection(-45, 0).y).toBeLessThan(0);
    });
});

describe('orbitRangeForBounds', () => {
    it('centers on the middle of the given bounds', () => {
        const range = orbitRangeForBounds({
            minX: 0,
            maxX: 10,
            minY: 2,
            maxY: 6,
            minZ: -4,
            maxZ: 4,
        });

        expect(range.center).toEqual({ x: 5, y: 4, z: 0 });
    });

    it("uses the bounds' diagonal as the default distance, at least the minimum", () => {
        const wide = orbitRangeForBounds({
            minX: 0,
            maxX: 30,
            minY: 0,
            maxY: 10,
            minZ: 0,
            maxZ: 40,
        });
        expect(wide.defaultDistance).toBeCloseTo(Math.hypot(30, 10, 40));
        expect(wide.maxDistance).toBeGreaterThan(wide.defaultDistance);

        const tiny = orbitRangeForBounds({
            minX: 0,
            maxX: 0.1,
            minY: 0,
            maxY: 0.1,
            minZ: 0,
            maxZ: 0.1,
        });
        expect(tiny.defaultDistance).toBe(ORBIT_MIN_DISTANCE);
    });
});

describe('defaultOrbitState', () => {
    it("starts at the standard three-quarter angle, at the range's default distance", () => {
        const range = orbitRangeForBounds({
            minX: 0,
            maxX: 10,
            minY: 0,
            maxY: 10,
            minZ: 0,
            maxZ: 10,
        });

        expect(defaultOrbitState(range)).toEqual({
            yawDegrees: ORBIT_DEFAULT_YAW_DEGREES,
            pitchDegrees: ORBIT_DEFAULT_PITCH_DEGREES,
            distance: range.defaultDistance,
        });
    });
});

describe('stepOrbitPitch', () => {
    it('pitches up when dragging up, like stepPitch', () => {
        expect(stepOrbitPitch(45, -10, 1)).toBe(55);
    });

    it('clamps to the orbit-specific range, never reaching the poles', () => {
        expect(stepOrbitPitch(80, -100, 1)).toBe(ORBIT_PITCH_MAX_DEGREES);
        expect(stepOrbitPitch(10, 100, 1)).toBe(ORBIT_PITCH_MIN_DEGREES);
    });
});

describe('stepOrbitDistance', () => {
    const range = orbitRangeForBounds({
        minX: 0,
        maxX: 10,
        minY: 0,
        maxY: 10,
        minZ: 0,
        maxZ: 10,
    });

    it('zooms out on a positive wheel delta (scrolling down)', () => {
        expect(stepOrbitDistance(10, 10, range, 0.01)).toBeCloseTo(11);
    });

    it('zooms in on a negative wheel delta (scrolling up)', () => {
        expect(stepOrbitDistance(10, -10, range, 0.01)).toBeCloseTo(9);
    });

    it('clamps to the range', () => {
        expect(stepOrbitDistance(range.minDistance, -1000, range, 0.01)).toBe(
            range.minDistance,
        );
        expect(stepOrbitDistance(range.maxDistance, 1000, range, 0.01)).toBe(
            range.maxDistance,
        );
    });
});

describe('orbitDistanceFromPinch', () => {
    const range = orbitRangeForBounds({
        minX: 0,
        maxX: 10,
        minY: 0,
        maxY: 10,
        minZ: 0,
        maxZ: 10,
    });

    it('zooms in (shrinks distance) as fingers spread further apart', () => {
        const result = orbitDistanceFromPinch(20, 100, 200, range);
        expect(result).toBeCloseTo(10);
    });

    it('zooms out (grows distance) as fingers pinch closer together', () => {
        const result = orbitDistanceFromPinch(10, 200, 100, range);
        expect(result).toBeCloseTo(20);
    });

    it('is a no-op guard against division by zero when the pinch started at zero gap', () => {
        expect(orbitDistanceFromPinch(15, 0, 50, range)).toBe(15);
    });

    it('clamps to the range', () => {
        expect(
            orbitDistanceFromPinch(range.minDistance, 200, 1000, range),
        ).toBe(range.minDistance);
    });
});

describe('orbitCameraPosition', () => {
    it('sits directly in front of the center (along +Z) at yaw 0, pitch 0', () => {
        const range = orbitRangeForBounds({
            minX: -5,
            maxX: 5,
            minY: -5,
            maxY: 5,
            minZ: -5,
            maxZ: 5,
        });

        const position = orbitCameraPosition(range, {
            yawDegrees: 0,
            pitchDegrees: 0,
            distance: 10,
        });

        expect(position.x).toBeCloseTo(range.center.x);
        expect(position.y).toBeCloseTo(range.center.y);
        expect(position.z).toBeCloseTo(range.center.z + 10);
    });

    it('rises above the center as pitch increases toward looking straight down', () => {
        const range = orbitRangeForBounds({
            minX: -5,
            maxX: 5,
            minY: -5,
            maxY: 5,
            minZ: -5,
            maxZ: 5,
        });

        const low = orbitCameraPosition(range, {
            yawDegrees: 0,
            pitchDegrees: 10,
            distance: 10,
        });
        const high = orbitCameraPosition(range, {
            yawDegrees: 0,
            pitchDegrees: 80,
            distance: 10,
        });

        expect(high.y).toBeGreaterThan(low.y);
    });

    it('stays at the same distance from center regardless of yaw', () => {
        const range = orbitRangeForBounds({
            minX: -5,
            maxX: 5,
            minY: -5,
            maxY: 5,
            minZ: -5,
            maxZ: 5,
        });
        const orbit = { yawDegrees: 130, pitchDegrees: 20, distance: 15 };
        const position = orbitCameraPosition(range, orbit);

        const distanceFromCenter = Math.hypot(
            position.x - range.center.x,
            position.y - range.center.y,
            position.z - range.center.z,
        );
        expect(distanceFromCenter).toBeCloseTo(15);
    });
});

describe('SPRINT_MULTIPLIER', () => {
    it('is greater than 1, so sprinting is actually faster than WALK_SPEED', () => {
        expect(SPRINT_MULTIPLIER).toBeGreaterThan(1);
    });
});

describe('stepOrbitStateByKeys', () => {
    const range = orbitRangeForBounds({
        minX: 0,
        maxX: 10,
        minY: 0,
        maxY: 10,
        minZ: 0,
        maxZ: 10,
    });
    const orbit = { yawDegrees: 0, pitchDegrees: 45, distance: 10 };

    it('yaws right/left on the right/left keys, mirroring drag-to-orbit', () => {
        const right = stepOrbitStateByKeys(orbit, new Set(['right']), 1, range);
        expect(right.yawDegrees).toBeGreaterThan(0);

        // Left decreases yaw, wrapping negative values into [0, 360).
        const left = stepOrbitStateByKeys(orbit, new Set(['left']), 1, range);
        expect(left.yawDegrees).toBeGreaterThan(270);
        expect(left.yawDegrees).toBeLessThan(360);
    });

    it('pitches up/down on the forward/backward keys, clamped to the orbit pitch range', () => {
        const up = stepOrbitStateByKeys(orbit, new Set(['forward']), 1, range);
        expect(up.pitchDegrees).toBeGreaterThan(orbit.pitchDegrees);

        const clamped = stepOrbitStateByKeys(
            orbit,
            new Set(['forward']),
            100,
            range,
        );
        expect(clamped.pitchDegrees).toBe(ORBIT_PITCH_MAX_DEGREES);
    });

    it('zooms in/out on the up/down keys, clamped to the range and always positive', () => {
        const zoomedIn = stepOrbitStateByKeys(orbit, new Set(['up']), 1, range);
        expect(zoomedIn.distance).toBeLessThan(orbit.distance);
        expect(zoomedIn.distance).toBeGreaterThan(0);

        const zoomedOut = stepOrbitStateByKeys(
            orbit,
            new Set(['down']),
            1,
            range,
        );
        expect(zoomedOut.distance).toBeGreaterThan(orbit.distance);

        const clamped = stepOrbitStateByKeys(
            orbit,
            new Set(['up']),
            100,
            range,
        );
        expect(clamped.distance).toBe(range.minDistance);
    });

    it('does not change anything when no direction is held', () => {
        expect(stepOrbitStateByKeys(orbit, new Set(), 1, range)).toEqual(orbit);
    });
});

describe('miniMapPercentX / miniMapPercentZ / miniMapPosition', () => {
    const bounds = {
        minX: 0,
        maxX: 10,
        minY: 0,
        maxY: 10,
        minZ: 0,
        maxZ: 20,
    };

    it('maps X onto [0, 100] mirrored — screen-right is world -X, matching the walk camera (see stepPosition)', () => {
        expect(miniMapPercentX(0, bounds)).toBe(100);
        expect(miniMapPercentX(10, bounds)).toBe(0);
        expect(miniMapPercentX(5, bounds)).toBe(50);
    });

    it('maps Z onto [0, 100] inverted — a larger Z is a smaller percent ("up")', () => {
        expect(miniMapPercentZ(0, bounds)).toBe(100);
        expect(miniMapPercentZ(20, bounds)).toBe(0);
        expect(miniMapPercentZ(10, bounds)).toBe(50);
    });

    it('clamps out-of-bounds positions instead of overflowing [0, 100]', () => {
        expect(miniMapPercentX(-5, bounds)).toBe(100);
        expect(miniMapPercentX(50, bounds)).toBe(0);
    });

    it('combines both axes into a single leftPercent/topPercent position', () => {
        expect(miniMapPosition({ x: 5, y: 0, z: 10 }, bounds)).toEqual({
            leftPercent: 50,
            topPercent: 50,
        });
    });
});

describe('miniMapRowLeftPercents', () => {
    it('returns one left% per row, spaced along the (mirrored) X axis like rowWorldX', () => {
        const bounds = boundsForWarehouse(3, 5, 1);

        const percents = miniMapRowLeftPercents(3, bounds);

        expect(percents).toHaveLength(3);
        // rowWorldX increases with row index, but miniMapPercentX mirrors X
        // (screen-right is world -X, matching the walk camera), so later
        // rows sit at a *smaller* left% than earlier ones.
        expect(percents[0]).toBeGreaterThan(percents[1]);
        expect(percents[1]).toBeGreaterThan(percents[2]);
    });

    it('returns an empty array for zero rows', () => {
        const bounds = boundsForWarehouse(0, 1, 1);

        expect(miniMapRowLeftPercents(0, bounds)).toEqual([]);
    });
});

describe('miniMapHeadingDegrees', () => {
    it('points "up" (0deg) at yaw 0, matching the default +Z look direction', () => {
        expect(miniMapHeadingDegrees(0)).toBe(0);
    });

    it('matches yaw directly (wrapped into [0, 360)) since the mini-map mirrors X the same way the walk camera does', () => {
        expect(miniMapHeadingDegrees(90)).toBe(90);
        expect(miniMapHeadingDegrees(270)).toBe(270);
        expect(miniMapHeadingDegrees(180)).toBe(180);
    });

    it('is consistent with miniMapPercentX: turning right (increasing yaw) points the arrow toward the mini-map side that strafing right actually moves the marker to', () => {
        // Facing yaw 0 and strafing right decreases world X (stepPosition's
        // documented handedness), which miniMapPercentX now maps to a
        // *larger* left% (mirrored) — i.e. strafing right moves the marker
        // toward the mini-map's right side, matching a small right turn
        // rotating the heading arrow toward that same side (degrees > 0,
        // clockwise, within the first quadrant).
        const bounds = {
            minX: -10,
            maxX: 10,
            minY: 0,
            maxY: 0,
            minZ: 0,
            maxZ: 0,
        };
        const centerLeftPercent = miniMapPercentX(0, bounds);
        const strafedRightLeftPercent = miniMapPercentX(-1, bounds);

        expect(strafedRightLeftPercent).toBeGreaterThan(centerLeftPercent);
        expect(miniMapHeadingDegrees(10)).toBeGreaterThan(0);
        expect(miniMapHeadingDegrees(10)).toBeLessThan(90);
    });
});
