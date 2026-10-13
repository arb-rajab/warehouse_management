import { describe, expect, it } from 'vitest';
import { WarehouseGeometry } from './geometry';
import {
    bumpDirection,
    nextLevelIndex,
    planGuidedStep,
    quarter,
    snapX,
    snapZ,
    snappedPose,
    wrapAngle,
} from './guidedMath';
import { LAYOUT } from './layout';

const geo = new WarehouseGeometry(4, 10, 3);
const inAisle = (z: number, yaw = 0) => ({
    x: geo.aisleCx(0),
    y: geo.levelEye(1),
    z,
    yaw,
    pitch: 0,
});

describe('quarter / wrapAngle', () => {
    it('snaps to the nearest right angle', () => {
        expect(quarter(0.2)).toBeCloseTo(0);
        expect(quarter(Math.PI / 2 - 0.1)).toBeCloseTo(Math.PI / 2);
        expect(quarter(-Math.PI / 2 + 0.3)).toBeCloseTo(-Math.PI / 2);
    });

    it('wraps an angle into half a turn either way', () => {
        expect(wrapAngle(3 * Math.PI)).toBeCloseTo(Math.PI);
        expect(wrapAngle((-3 * Math.PI) / 2)).toBeCloseTo(Math.PI / 2);
    });
});

describe('snapping', () => {
    it('snaps x to the aisle centre or one bay beside it', () => {
        const cx = geo.aisleCx(0);

        expect(snapX(geo, cx + 0.3)).toBeCloseTo(cx);
        expect(snapX(geo, cx + 1.5)).toBeCloseTo(cx + LAYOUT.colSpacing);
        expect(snapX(geo, cx - 2.6)).toBeCloseTo(cx - LAYOUT.colSpacing);
        expect(snapX(geo, cx - 4)).toBeCloseTo(cx - LAYOUT.colSpacing);
    });

    it('snaps z to a bay line', () => {
        expect(snapZ(geo, 3.4)).toBeCloseTo(3.2);
        expect(snapZ(geo, -0.3)).toBeCloseTo(0);
    });

    it('snaps a whole pose to a bay, a shelf height and a facing', () => {
        const pose = snappedPose(geo, {
            ...inAisle(3.3, 0.2),
            y: geo.levelEye(2) + 0.2,
            pitch: 0.3,
        });

        expect(pose.z).toBeCloseTo(3.2);
        expect(pose.yaw).toBeCloseTo(0);
        expect(pose.pitch).toBe(0);
        expect(pose.levelIndex).toBe(2);
        expect(pose.y).toBeCloseTo(geo.levelEye(2));
    });
});

describe('planGuidedStep', () => {
    it('steps one bay along the facing', () => {
        const step = planGuidedStep(geo, inAisle(4.8), 1, 0)!;

        expect(step.z).toBeCloseTo(4.8 + LAYOUT.colSpacing);
        expect(step.x).toBeCloseTo(geo.aisleCx(0));
        expect(step.bays).toBe(1);
    });

    it('steps backwards and four bays at once', () => {
        expect(planGuidedStep(geo, inAisle(4.8), -1, 0)!.z).toBeCloseTo(3.2);
        expect(planGuidedStep(geo, inAisle(1.6), 4, 0)!.z).toBeCloseTo(
            1.6 + 4 * 1.6,
        );
    });

    it('shortens a long step that would leave the building', () => {
        const near = inAisle(geo.bounds.maxZ + 8 - 1.7 * LAYOUT.colSpacing);
        const step = planGuidedStep(geo, near, 4, 0)!;

        expect(step.bays).toBeLessThan(4);
        expect(step.bays).toBeGreaterThanOrEqual(1);
    });

    it('side-steps relative to the facing', () => {
        // facing +z, the right-hand side is -x
        expect(planGuidedStep(geo, inAisle(4.8), 0, 1)!.x).toBeCloseTo(
            geo.aisleCx(0) - LAYOUT.colSpacing,
        );
        expect(planGuidedStep(geo, inAisle(4.8), 0, -1)!.x).toBeCloseTo(
            geo.aisleCx(0) + LAYOUT.colSpacing,
        );
    });

    it('is blocked by a rack: a step into one bumps', () => {
        // one bay off the aisle centre is the last free line; the next one is rack
        const edge = {
            x: geo.aisleCx(0) - LAYOUT.colSpacing,
            y: geo.levelEye(0),
            z: 4.8,
            yaw: 0,
            pitch: 0,
        };

        // stepping towards the rack (-x is the right of a +z facing)
        expect(planGuidedStep(geo, edge, 0, 1)).toBeNull();
        expect(planGuidedStep(geo, edge, 0, 4)).toBeNull();
    });

    it('turns the facing into a quarter before stepping', () => {
        const step = planGuidedStep(geo, inAisle(4.8, 0.2), 1, 0)!;

        expect(step.yaw).toBeCloseTo(0);
    });
});

describe('bumpDirection', () => {
    it('points the way the blocked step was heading', () => {
        const d = bumpDirection(inAisle(0), 1, 0);

        expect(d.dz).toBeCloseTo(1);
        expect(d.dx).toBeCloseTo(0);
    });
});

describe('nextLevelIndex', () => {
    it('moves between shelves and clamps at the ends', () => {
        expect(nextLevelIndex(geo, 0, 1)).toBe(1);
        expect(nextLevelIndex(geo, 2, 1)).toBe(2);
        expect(nextLevelIndex(geo, 0, -1)).toBe(0);
    });
});
