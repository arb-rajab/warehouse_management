import { describe, expect, it } from 'vitest';
import {
    boundsForWarehouse,
    CELL_SPACING,
    cellWorldZ,
    clamp,
    EYE_HEIGHT,
    facedGridCoordinate,
    FLAT_SPACING,
    flatWorldY,
    lookDirection,
    MIN_EYE_HEIGHT,
    moveDirectionForKey,
    nearestCellNumber,
    nearestFlatNumber,
    nearestRowIndex,
    normalizeYaw,
    PITCH_MAX_DEGREES,
    PITCH_MIN_DEGREES,
    pitchToLookAt,
    ROW_SPACING,
    rowWorldX,
    stepPitch,
    stepPosition,
    stepYaw,
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
