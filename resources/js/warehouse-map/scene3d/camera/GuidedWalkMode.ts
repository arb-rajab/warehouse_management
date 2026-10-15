import {
    bumpDirection,
    nextLevelIndex,
    planGuidedStep,
    quarter,
    snappedPose,
    wrapAngle,
} from '../../domain/guidedMath';
import type { Intent } from '../../input/Intent';
import { angleDelta, clamp, easeInOutCubic } from '../../utils/math';
import type { CameraContext, CameraMode, Pose } from './types';
import type { WalkState } from './WalkState';

const PITCH_LIMIT = 1.35;
const DRAG_RATE = { mouse: 0.0032, touch: 0.0042 };
const WHEEL_STEP = 40;

interface Glide {
    from: Pose;
    to: Pose;
    startedAt: number;
    duration: number;
    done?: () => void;
}

/**
 * Precise work: the camera rolls like a cart on rails, one bay (1.6 m) per
 * step, 90 degree turns, snapping between shelf heights. The mouse stays
 * visible; dragging peeks around and the view snaps back to the nearest
 * facing on release (unless auto-align is off).
 */
export class GuidedWalkMode implements CameraMode {
    readonly id = 'guided' as const;
    private ctx!: CameraContext;
    private glide: Glide | null = null;
    private wheelAcc = 0;

    constructor(
        private readonly walk: WalkState,
        private readonly clock: () => number = () => performance.now(),
    ) {}

    get isMoving(): boolean {
        return this.glide !== null;
    }

    enter(ctx: CameraContext): void {
        this.ctx = ctx;
        this.glide = null;
        this.wheelAcc = 0;
    }

    exit(): void {
        this.glide = null;
        this.wheelAcc = 0;
    }

    pose(): Pose {
        return this.walk.pose;
    }

    /** Brings the cart to the nearest bay, shelf height and facing (used when switching in from free walk). */
    snap(): void {
        const snapped = snappedPose(this.ctx.geometry, this.walk.pose);

        this.walk.eyeIndex = snapped.levelIndex;
        this.glideTo(
            {
                ...this.walk.pose,
                x: snapped.x,
                y: snapped.y,
                z: snapped.z,
                yaw: snapped.yaw,
                pitch: 0,
            },
            380,
        );
    }

    handle(intent: Intent): boolean {
        switch (intent.type) {
            case 'step':
                this.step(intent.forward, intent.strafe);

                return true;
            case 'turn':
                this.turn(intent.quarters);

                return true;
            case 'changeLevel':
                this.changeLevel(intent.delta);

                return true;
            case 'wheel':
                // one notch of the wheel = one bay
                this.wheelAcc += intent.delta;

                if (Math.abs(this.wheelAcc) > WHEEL_STEP) {
                    this.step(this.wheelAcc < 0 ? 1 : -1, 0);
                    this.wheelAcc = 0;
                }

                return true;
            case 'drag':
                if (!this.glide && !intent.secondary) {
                    this.peek(
                        -intent.dx,
                        -intent.dy,
                        intent.pointer === 'touch'
                            ? DRAG_RATE.touch
                            : DRAG_RATE.mouse,
                    );
                }

                return true;
            case 'dragEnd':
                if (intent.moved) {
                    this.settle();
                }

                return true;
            default:
                return false;
        }
    }

    update(dt: number, now: number): boolean {
        let active = false;

        if (this.glide) {
            this.tickGlide(now);
            active = true;
        }

        if (this.easeFov(dt)) {
            active = true;
        }

        if (active) {
            this.ctx.apply(this.walk.pose, 0);
        }

        return active;
    }

    /** Snaps the facing back to the nearest quarter turn after a peek. */
    settle(): void {
        if (this.ctx.settings().magnet && !this.glide) {
            this.glideTo(
                {
                    ...this.walk.pose,
                    yaw: quarter(this.walk.pose.yaw),
                    pitch: 0,
                },
                260,
            );
        }
    }

    private step(forward: number, strafe: number): void {
        if (this.glide) {
            return;
        }

        const pose = this.walk.pose;
        const step = planGuidedStep(this.ctx.geometry, pose, forward, strafe);

        if (step) {
            this.glideTo(
                { ...pose, x: step.x, z: step.z, yaw: step.yaw, pitch: 0 },
                240 + 50 * step.bays,
            );
            this.ctx.buzz(8);

            return;
        }

        const bump = bumpDirection(pose, forward, strafe);

        this.nudge(bump.dx, bump.dz);
    }

    private turn(quarters: number): void {
        if (this.glide) {
            return;
        }

        const pose = this.walk.pose;

        this.glideTo(
            {
                ...pose,
                yaw: quarter(pose.yaw) + (quarters * Math.PI) / 2,
                pitch: 0,
            },
            300,
        );
    }

    private changeLevel(delta: number): void {
        if (this.glide) {
            return;
        }

        const pose = this.walk.pose;
        const index = nextLevelIndex(
            this.ctx.geometry,
            this.walk.eyeIndex,
            delta,
        );

        if (index === this.walk.eyeIndex) {
            this.nudge(0, 0);

            return;
        }

        this.walk.eyeIndex = index;
        this.glideTo(
            {
                ...pose,
                y: this.ctx.geometry.levelEye(index),
                yaw: quarter(pose.yaw),
                pitch: 0,
            },
            320,
        );
    }

    /** A blocked step bumps: the cart shoves a little way and rolls back. */
    private nudge(dx: number, dz: number): void {
        const pose = this.walk.pose;
        const home = { ...pose };

        this.glideTo(
            { ...pose, x: pose.x + dx * 0.22, z: pose.z + dz * 0.22 },
            110,
            () => this.glideTo(home, 160),
        );
        this.ctx.buzz(20);
    }

    private peek(dx: number, dy: number, rate: number): void {
        const s = this.ctx.settings();
        const k = rate * s.sens;
        const w = this.walk.pose;

        w.yaw -= dx * k;
        w.pitch = clamp(
            w.pitch - dy * k * (s.invertY ? -1 : 1),
            -PITCH_LIMIT,
            PITCH_LIMIT,
        );
        this.ctx.apply(w, 0);
    }

    private glideTo(to: Pose, duration: number, done?: () => void): void {
        this.glide = {
            from: { ...this.walk.pose },
            to,
            startedAt: this.clock(),
            duration,
            done,
        };
    }

    private tickGlide(now: number): void {
        const g = this.glide!;
        const u = Math.min(1, (now - g.startedAt) / g.duration);
        const e = easeInOutCubic(u);
        const a = g.from;
        const b = g.to;
        const w = this.walk.pose;

        w.x = a.x + (b.x - a.x) * e;
        w.y = a.y + (b.y - a.y) * e;
        w.z = a.z + (b.z - a.z) * e;
        w.yaw = a.yaw + angleDelta(a.yaw, b.yaw) * e;
        w.pitch = a.pitch + (b.pitch - a.pitch) * e;

        if (u >= 1) {
            w.yaw = wrapAngle(b.yaw);
            this.glide = null;
            g.done?.();
        }
    }

    private easeFov(dt: number): boolean {
        const w = this.walk.pose;
        const target = this.ctx.settings().fov;

        if (Math.abs(w.fov - target) < 0.05) {
            if (w.fov !== target) {
                w.fov = target;

                return true;
            }

            return false;
        }

        w.fov += (target - w.fov) * Math.min(1, dt * 10);

        return true;
    }
}
