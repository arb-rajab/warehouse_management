import { describe, expect, it, vi } from 'vitest';
import { DEFAULT_SETTINGS } from '../../config';
import { WarehouseGeometry } from '../../domain/geometry';
import { WallCollider } from '../collision/WallCollider';
import { CameraRig } from './CameraRig';
import { FreeWalkMode } from './FreeWalkMode';
import { GuidedWalkMode } from './GuidedWalkMode';
import { OverviewMode } from './OverviewMode';
import type { CameraContext, Pose } from './types';
import { WalkState } from './WalkState';

const geometry = new WarehouseGeometry(4, 10, 3);

function makeContext(settings = { ...DEFAULT_SETTINGS }) {
    const applied: Pose[] = [];
    const ctx: CameraContext = {
        geometry,
        collider: new WallCollider(geometry),
        settings: () => settings,
        apply: (pose) => applied.push({ ...pose }),
        viewHeight: () => 800,
        buzz: vi.fn(),
    };

    return { ctx, applied, settings };
}

function startWalk(): WalkState {
    const walk = new WalkState();

    walk.set({
        x: geometry.aisleCx(0),
        y: 1.7,
        z: 4.8,
        yaw: 0,
        pitch: 0,
        fov: 62,
    });

    return walk;
}

describe('FreeWalkMode', () => {
    it('walks along the facing while a forward axis is held, and stops when released', () => {
        const { ctx } = makeContext();
        const walk = startWalk();
        const mode = new FreeWalkMode(walk);

        mode.enter(ctx);
        mode.handle({
            type: 'move',
            source: 'keyboard',
            forward: 1,
            strafe: 0,
        });

        for (let i = 0; i < 60; i++) {
            mode.update(1 / 60);
        }

        expect(walk.pose.z).toBeGreaterThan(4.8 + 2);
        expect(walk.pose.x).toBeCloseTo(geometry.aisleCx(0), 3);

        mode.handle({
            type: 'move',
            source: 'keyboard',
            forward: 0,
            strafe: 0,
        });

        for (let i = 0; i < 120; i++) {
            mode.update(1 / 60);
        }

        const stopped = walk.pose.z;

        mode.update(1 / 60);
        expect(walk.pose.z).toBeCloseTo(stopped, 3);
    });

    it('sprints faster than it walks', () => {
        const run = (sprint: boolean) => {
            const { ctx } = makeContext();
            const walk = startWalk();
            const mode = new FreeWalkMode(walk);

            mode.enter(ctx);
            mode.handle({
                type: 'move',
                source: 'keyboard',
                forward: 1,
                strafe: 0,
            });

            if (sprint) {
                mode.handle({ type: 'sprint', source: 'keyboard', on: true });
            }

            for (let i = 0; i < 60; i++) {
                mode.update(1 / 60);
            }

            return walk.pose.z - 4.8;
        };

        expect(run(true)).toBeGreaterThan(run(false) * 1.5);
    });

    it('adds up two devices held at once instead of letting one overwrite the other', () => {
        const { ctx } = makeContext();
        const walk = startWalk();
        const mode = new FreeWalkMode(walk);

        mode.enter(ctx);
        mode.handle({
            type: 'move',
            source: 'keyboard',
            forward: 1,
            strafe: 0,
        });
        mode.handle({ type: 'move', source: 'gamepad', forward: 0, strafe: 0 });

        for (let i = 0; i < 30; i++) {
            mode.update(1 / 60);
        }

        expect(walk.pose.z).toBeGreaterThan(4.8);
    });

    it('cannot walk through a rack', () => {
        const { ctx } = makeContext();
        const walk = startWalk();
        const mode = new FreeWalkMode(walk);

        walk.pose.x = geometry.rowX(0) - 2; // just inside the aisle next to rack A
        mode.enter(ctx);
        mode.handle({
            type: 'move',
            source: 'keyboard',
            forward: 0,
            strafe: -1,
        }); // strafe left = +x

        for (let i = 0; i < 240; i++) {
            mode.update(1 / 60);
        }

        expect(walk.pose.x).toBeLessThan(geometry.rowX(0));
    });

    it('rises with the lift axis and can fly over the racks', () => {
        const { ctx } = makeContext();
        const walk = startWalk();
        const mode = new FreeWalkMode(walk);

        mode.enter(ctx);
        mode.handle({ type: 'lift', source: 'keyboard', value: 1 });

        for (let i = 0; i < 240; i++) {
            mode.update(1 / 60);
        }

        expect(walk.pose.y).toBeGreaterThan(geometry.height);
    });

    it('looks around: a look delta turns the view, vertical look is clamped, and invert flips it', () => {
        const { ctx, settings } = makeContext();
        const walk = startWalk();
        const mode = new FreeWalkMode(walk);

        mode.enter(ctx);
        mode.handle({ type: 'look', dx: 100, dy: 0, rate: 0.01 });
        expect(walk.pose.yaw).toBeCloseTo(-1); // moving right turns right, which lowers yaw

        mode.handle({ type: 'look', dx: 0, dy: -10_000, rate: 0.01 });
        expect(walk.pose.pitch).toBeCloseTo(1.35);

        walk.pose.pitch = 0;
        settings.invertY = true;
        mode.handle({ type: 'look', dx: 0, dy: 50, rate: 0.01 });
        expect(walk.pose.pitch).toBeGreaterThan(0);
    });

    it('drag looks in the opposite direction to the hand, like grabbing the view', () => {
        const { ctx } = makeContext();
        const walk = startWalk();
        const mode = new FreeWalkMode(walk);

        mode.enter(ctx);
        mode.handle({
            type: 'drag',
            dx: 100,
            dy: 0,
            secondary: false,
            pointer: 'mouse',
        });

        expect(walk.pose.yaw).toBeGreaterThan(0);
    });

    it('zooms the lens like a scope while held, and the wheel changes the field of view', () => {
        const { ctx } = makeContext();
        const walk = startWalk();
        const mode = new FreeWalkMode(walk);

        mode.enter(ctx);
        mode.handle({ type: 'zoomLens', on: true });

        for (let i = 0; i < 120; i++) {
            mode.update(1 / 60);
        }

        expect(walk.pose.fov).toBeCloseTo(30, 0);

        mode.handle({ type: 'zoomLens', on: false });
        mode.handle({ type: 'wheel', delta: 400 });

        for (let i = 0; i < 120; i++) {
            mode.update(1 / 60);
        }

        expect(walk.pose.fov).toBeCloseTo(74, 0);
    });

    it('ignores intents meant for other modes', () => {
        const { ctx } = makeContext();
        const mode = new FreeWalkMode(startWalk());

        mode.enter(ctx);

        expect(mode.handle({ type: 'step', forward: 1, strafe: 0 })).toBe(
            false,
        );
        expect(mode.handle({ type: 'turn', quarters: 1 })).toBe(false);
    });
});

describe('GuidedWalkMode', () => {
    function guided() {
        const { ctx, applied, settings } = makeContext();
        const walk = startWalk();
        let clock = 0;
        const mode = new GuidedWalkMode(walk, () => clock);

        mode.enter(ctx);

        const run = (ms: number) => {
            clock += ms;
            mode.update(ms / 1000, clock);
        };

        return { mode, walk, run, ctx, applied, settings };
    }

    it('steps exactly one bay forward per step', () => {
        const { mode, walk, run } = guided();

        mode.handle({ type: 'step', forward: 1, strafe: 0 });
        run(2000);

        expect(walk.pose.z).toBeCloseTo(4.8 + 1.6, 3);
        expect(mode.isMoving).toBe(false);
    });

    it('ignores a second step while still gliding', () => {
        const { mode, walk, run } = guided();

        mode.handle({ type: 'step', forward: 1, strafe: 0 });
        mode.handle({ type: 'step', forward: 1, strafe: 0 });
        run(2000);

        expect(walk.pose.z).toBeCloseTo(4.8 + 1.6, 3);
    });

    it('steps four bays with a long step', () => {
        const { mode, walk, run } = guided();

        mode.handle({ type: 'step', forward: 4, strafe: 0 });
        run(2000);

        expect(walk.pose.z).toBeCloseTo(4.8 + 6.4, 3);
    });

    it('side-steps one bay and turns ninety degrees', () => {
        const { mode, walk, run } = guided();

        mode.handle({ type: 'step', forward: 0, strafe: -1 });
        run(2000);
        expect(walk.pose.x).toBeCloseTo(geometry.aisleCx(0) + 1.6, 3);

        mode.handle({ type: 'turn', quarters: 1 });
        run(2000);
        expect(walk.pose.yaw).toBeCloseTo(Math.PI / 2, 3);
    });

    it('bumps instead of walking into a rack, and comes back', () => {
        const { mode, walk, run, ctx } = guided();

        walk.pose.x = geometry.aisleCx(0) - 1.6; // the last free line beside the rack

        mode.handle({ type: 'step', forward: 0, strafe: 1 }); // towards the rack
        run(60);
        expect(mode.isMoving).toBe(true);

        run(2000);
        run(2000);

        expect(walk.pose.x).toBeCloseTo(geometry.aisleCx(0) - 1.6, 3);
        expect(ctx.buzz).toHaveBeenCalledWith(20);
    });

    it('moves between shelf heights and stops at the top and bottom', () => {
        const { mode, walk, run } = guided();

        walk.eyeIndex = 1;
        mode.handle({ type: 'changeLevel', delta: 1 });
        run(2000);
        expect(walk.pose.y).toBeCloseTo(geometry.levelEye(2), 3);

        mode.handle({ type: 'changeLevel', delta: 1 }); // already on top: a bump
        run(2000);
        run(2000);
        expect(walk.pose.y).toBeCloseTo(geometry.levelEye(2), 3);
        expect(walk.eyeIndex).toBe(2);
    });

    it('turns one wheel notch into one step', () => {
        const { mode, walk, run } = guided();

        mode.handle({ type: 'wheel', delta: -20 });
        run(100);
        expect(walk.pose.z).toBeCloseTo(4.8, 1);

        mode.handle({ type: 'wheel', delta: -30 });
        run(2000);
        expect(walk.pose.z).toBeCloseTo(4.8 + 1.6, 3);
    });

    it('peeks with a drag and snaps back to the nearest facing on release', () => {
        const { mode, walk, run } = guided();

        mode.handle({
            type: 'drag',
            dx: -100,
            dy: 0,
            secondary: false,
            pointer: 'mouse',
        });
        expect(walk.pose.yaw).not.toBeCloseTo(0);

        mode.handle({ type: 'dragEnd', moved: true });
        run(2000);

        expect(walk.pose.yaw).toBeCloseTo(0, 3);
        expect(walk.pose.pitch).toBeCloseTo(0, 3);
    });

    it('stays where it peeked when auto-align is off', () => {
        const { mode, walk, run, settings } = guided();

        settings.magnet = false;
        mode.handle({
            type: 'drag',
            dx: -100,
            dy: 0,
            secondary: false,
            pointer: 'mouse',
        });

        const yaw = walk.pose.yaw;

        mode.handle({ type: 'dragEnd', moved: true });
        run(2000);

        expect(walk.pose.yaw).toBeCloseTo(yaw, 5);
    });

    it('snaps to the nearest bay, height and facing when switched in from free walk', () => {
        const { mode, walk, run } = guided();

        walk.pose = {
            ...walk.pose,
            z: 5.3,
            yaw: 0.2,
            y: geometry.levelEye(2) + 0.1,
        };
        mode.snap();
        run(2000);

        expect(walk.pose.z).toBeCloseTo(4.8, 3);
        expect(walk.pose.yaw).toBeCloseTo(0, 3);
        expect(walk.pose.y).toBeCloseTo(geometry.levelEye(2), 3);
        expect(walk.eyeIndex).toBe(2);
    });
});

describe('OverviewMode', () => {
    function overview() {
        const { ctx, applied } = makeContext();
        const mode = new OverviewMode({
            px: geometry.bounds.cx,
            pz: geometry.bounds.cz,
            yaw: 0,
            pitch: -0.72,
            dist: 60,
            fov: 50,
        });

        mode.enter(ctx);
        mode.setFit(60);

        return { mode, applied };
    }

    it('looks at the pivot from the orbit distance', () => {
        const { mode } = overview();
        const pose = mode.pose();
        const dist = Math.hypot(
            pose.x - mode.snapshot().px,
            pose.y,
            pose.z - mode.snapshot().pz,
        );

        expect(dist).toBeCloseTo(60, 3);
        expect(pose.y).toBeGreaterThan(0);
    });

    it('orbits with a primary drag, never below the floor or past straight down', () => {
        const { mode } = overview();

        mode.handle({
            type: 'drag',
            dx: 100,
            dy: 0,
            secondary: false,
            pointer: 'mouse',
        });
        expect(mode.snapshot().yaw).toBeLessThan(0);

        mode.handle({
            type: 'drag',
            dx: 0,
            dy: -100_000,
            secondary: false,
            pointer: 'mouse',
        });
        expect(mode.snapshot().pitch).toBeCloseTo(-0.12);

        mode.handle({
            type: 'drag',
            dx: 0,
            dy: 100_000,
            secondary: false,
            pointer: 'mouse',
        });
        expect(mode.snapshot().pitch).toBeCloseTo(-1.45);
    });

    it('pans with a secondary drag and keeps the pivot over the warehouse', () => {
        const { mode } = overview();
        const before = mode.snapshot();

        mode.handle({
            type: 'drag',
            dx: 50,
            dy: 0,
            secondary: true,
            pointer: 'mouse',
        });
        expect(mode.snapshot().px).not.toBeCloseTo(before.px);

        mode.handle({
            type: 'drag',
            dx: 1e7,
            dy: 0,
            secondary: true,
            pointer: 'mouse',
        });
        expect(Math.abs(mode.snapshot().px)).toBeLessThanOrEqual(
            geometry.bounds.maxX,
        );
    });

    it('zooms with the wheel within its limits', () => {
        const { mode } = overview();

        mode.handle({ type: 'wheel', delta: 100_000 });
        expect(mode.distance).toBeCloseTo(90); // 1.5 x the fit distance

        mode.handle({ type: 'wheel', delta: -100_000 });
        expect(mode.distance).toBeCloseTo(9);
    });

    it('pans with held keys while updating', () => {
        const { mode } = overview();
        const before = mode.snapshot();

        mode.handle({
            type: 'move',
            source: 'keyboard',
            forward: 1,
            strafe: 0,
        });
        expect(mode.update(0.1)).toBe(true);
        expect(mode.snapshot().pz).not.toBeCloseTo(before.pz);

        mode.handle({
            type: 'move',
            source: 'keyboard',
            forward: 0,
            strafe: 0,
        });
        expect(mode.update(0.1)).toBe(false);
    });

    it('keeps the whole-warehouse framing when the window changes shape, unless zoomed away', () => {
        const { mode } = overview();

        mode.setFit(80);
        expect(mode.distance).toBeCloseTo(80);

        mode.handle({ type: 'wheel', delta: -200 });
        const zoomed = mode.distance;

        mode.setFit(120);
        expect(mode.distance).toBeCloseTo(zoomed);
    });
});

describe('CameraRig', () => {
    function rig() {
        const { ctx, applied } = makeContext();
        const states: string[] = [];
        let clock = 0;
        const instance = new CameraRig(
            ctx,
            (state, mode) => states.push(`${state}:${mode}`),
            () => clock,
        );
        const walk = startWalk();
        const overviewMode = new OverviewMode({
            px: 0,
            pz: 0,
            yaw: 0,
            pitch: -0.72,
            dist: 60,
            fov: 50,
        });

        instance.register(overviewMode);
        instance.register(new FreeWalkMode(walk));
        instance.register(new GuidedWalkMode(walk, () => clock));
        instance.activate('overview');

        return {
            instance,
            applied,
            states,
            walk,
            advance: (ms: number) => (clock += ms),
        };
    }

    it('reports overview, walk and fly as it changes mode', () => {
        const { instance, states, advance } = rig();

        expect(instance.state).toBe('overview');

        instance.flyTo(
            { x: 0, y: 1.7, z: 0, yaw: 0, pitch: 0, fov: 62 },
            { duration: 100, done: () => instance.activate('free') },
        );
        expect(instance.state).toBe('fly');

        advance(200);
        instance.update(0.016, 200);

        expect(instance.state).toBe('walk');
        expect(instance.activeId).toBe('free');
        expect(states).toEqual([
            'overview:overview',
            'fly:overview',
            'walk:free',
        ]);
    });

    it('eases the camera along a flight and ends exactly on the target', () => {
        const { instance, applied, advance } = rig();
        const target = { x: 10, y: 2, z: 20, yaw: 1, pitch: 0, fov: 62 };

        instance.flyTo(target, { duration: 1000 });
        advance(500);
        instance.update(0.016, 500);

        const mid = applied.at(-1)!;

        expect(mid.x).toBeGreaterThan(0);
        expect(mid.x).toBeLessThan(10);

        advance(600);
        instance.update(0.016, 1100);

        expect(applied.at(-1)).toMatchObject({ x: 10, y: 2, z: 20, fov: 62 });
        expect(instance.isFlying).toBe(false);
    });

    it('takes the short way round when turning', () => {
        const { instance, applied, advance } = rig();

        instance.activate('free');
        instance.flyTo(
            { x: 0, y: 1.7, z: 0, yaw: -3, pitch: 0, fov: 62 },
            { duration: 1000 },
        );
        // from yaw 0 to -3 is the short way (not +3.28 round)
        advance(500);
        instance.update(0.016, 500);

        expect(applied.at(-1)!.yaw).toBeLessThan(0);
    });

    it('ignores intents while flying and passes them to the active mode otherwise', () => {
        const { instance } = rig();

        expect(instance.handle({ type: 'wheel', delta: 10 })).toBe(true);

        instance.flyTo({ x: 0, y: 1, z: 0, yaw: 0, pitch: 0, fov: 50 });
        expect(instance.handle({ type: 'wheel', delta: 10 })).toBe(false);
    });

    it('refuses a mode nobody registered', () => {
        const { instance } = rig();

        expect(() => instance.activate('nope' as never)).toThrow(/nope/);
    });

    it('swaps free and guided without knowing which is which (interchangeable)', () => {
        const { instance, walk } = rig();

        instance.activate('free');
        instance.activate('guided');
        expect(instance.handle({ type: 'step', forward: 1, strafe: 0 })).toBe(
            true,
        );

        instance.activate('free');
        expect(instance.handle({ type: 'step', forward: 1, strafe: 0 })).toBe(
            false,
        );
        expect(walk.pose.fov).toBe(62);
    });
});
