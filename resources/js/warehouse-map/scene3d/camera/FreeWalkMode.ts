import type { Intent, InputSource } from '../../input/Intent';
import { clamp } from '../../utils/math';
import type { CameraContext, CameraMode, Pose } from './types';
import type { WalkState } from './WalkState';

const PITCH_LIMIT = 1.35;
const SCOPE_FOV = 30;
const DRAG_RATE = { mouse: 0.0032, touch: 0.0042 };

/** Game-style walking: held keys / stick walk, the mouse looks, Space and Z lift. */
export class FreeWalkMode implements CameraMode {
    readonly id = 'free' as const;
    private ctx!: CameraContext;
    private readonly axes = new Map<
        InputSource,
        { forward: number; strafe: number }
    >();
    private readonly lifts = new Map<InputSource, number>();
    private readonly sprints = new Set<InputSource>();
    private lookRate = { x: 0, y: 0 };
    private vel = { x: 0, y: 0, z: 0 };
    private zoomHeld = false;
    private fovBase = 62;
    private bobPhase = 0;
    private moved = 0;

    constructor(private readonly walk: WalkState) {}

    /** Seconds spent actually walking since the mode was entered (the welcome card hides after a few). */
    get movedSeconds(): number {
        return this.moved;
    }

    enter(ctx: CameraContext): void {
        this.ctx = ctx;
        this.reset();
        this.fovBase = ctx.settings().fov;
    }

    exit(): void {
        this.reset();
    }

    /** Called when the operator changes the field of view in settings. */
    applySettings(): void {
        this.fovBase = this.ctx.settings().fov;
    }

    pose(): Pose {
        return this.walk.pose;
    }

    handle(intent: Intent): boolean {
        switch (intent.type) {
            case 'move':
                this.axes.set(intent.source, {
                    forward: intent.forward,
                    strafe: intent.strafe,
                });

                return true;
            case 'lift':
                this.lifts.set(intent.source, intent.value);

                return true;
            case 'sprint':
                if (intent.on) {
                    this.sprints.add(intent.source);
                } else {
                    this.sprints.delete(intent.source);
                }

                return true;
            case 'look':
                this.look(intent.dx, intent.dy, intent.rate);

                return true;
            case 'lookRate':
                this.lookRate = { x: intent.x, y: intent.y };

                return true;
            case 'drag':
                // dragging grabs the view, like Street View: the scene follows the hand
                if (!intent.secondary) {
                    this.look(
                        -intent.dx,
                        -intent.dy,
                        intent.pointer === 'touch'
                            ? DRAG_RATE.touch
                            : DRAG_RATE.mouse,
                    );
                }

                return true;
            case 'zoomLens':
                this.zoomHeld = intent.on;
                this.ctx.apply(this.walk.pose, this.walk.bobY);

                return true;
            case 'wheel':
                this.fovBase = clamp(
                    this.fovBase + intent.delta * 0.03,
                    30,
                    85,
                );

                return true;
            default:
                return false;
        }
    }

    update(dt: number): boolean {
        const w = this.walk.pose;
        const s = this.ctx.settings();
        let active = false;

        if (this.lookRate.x || this.lookRate.y) {
            w.yaw -= this.lookRate.x * dt * 2.6 * s.sens;
            w.pitch = clamp(
                w.pitch -
                    this.lookRate.y * dt * 2 * s.sens * (s.invertY ? -1 : 1),
                -PITCH_LIMIT,
                PITCH_LIMIT,
            );
            active = true;
        }

        let forward = 0;
        let strafe = 0;
        let lift = 0;

        this.axes.forEach((a) => {
            forward += a.forward;
            strafe += a.strafe;
        });
        this.lifts.forEach((v) => (lift += v));

        const magnitude = Math.hypot(forward, strafe);

        if (magnitude > 1) {
            forward /= magnitude;
            strafe /= magnitude;
        }

        lift = clamp(lift, -1, 1);

        const sprint = this.sprints.size > 0;
        const speed = (sprint ? 8.5 : 3.6) * s.speed;
        const sy = Math.sin(w.yaw);
        const cy = Math.cos(w.yaw);
        const ease = Math.min(1, dt * 9);
        const v = this.vel;

        v.x += ((sy * forward - cy * strafe) * speed - v.x) * ease;
        v.z += ((cy * forward + sy * strafe) * speed - v.z) * ease;
        v.y += (lift * 2.6 - v.y) * ease;

        const horizontal = Math.hypot(v.x, v.z);

        if (horizontal + Math.abs(v.y) < 0.004) {
            v.x = v.y = v.z = 0;
        } else {
            this.ctx.collider.move(w, v.x, v.y, v.z, dt);
            this.moved += dt;
            active = true;
        }

        if (this.bob(dt, s.bob && w.y < 2.4 && horizontal > 0.4, sprint)) {
            active = true;
        }

        if (this.easeFov(dt, sprint && horizontal > 0.4 ? 6 : 0)) {
            active = true;
        }

        if (active) {
            this.ctx.apply(w, this.walk.bobY);
        }

        return active;
    }

    private look(dx: number, dy: number, rate: number): void {
        const s = this.ctx.settings();
        const k = rate * s.sens;
        const w = this.walk.pose;

        w.yaw -= dx * k;
        w.pitch = clamp(
            w.pitch - dy * k * (s.invertY ? -1 : 1),
            -PITCH_LIMIT,
            PITCH_LIMIT,
        );
        this.ctx.apply(w, this.walk.bobY);
    }

    /** The small sway of walking. Returns true while it still moves. */
    private bob(dt: number, walking: boolean, sprint: boolean): boolean {
        if (walking) {
            this.bobPhase += dt * (sprint ? 12 : 8);
            this.walk.bobY = Math.sin(this.bobPhase) * (sprint ? 0.05 : 0.032);

            return true;
        }

        if (Math.abs(this.walk.bobY) > 0.0004) {
            this.walk.bobY *= Math.max(0, 1 - dt * 12);

            return true;
        }

        this.walk.bobY = 0;

        return false;
    }

    /** Eases the lens towards its target (wider while sprinting, narrow with the scope). */
    private easeFov(dt: number, kick: number): boolean {
        const w = this.walk.pose;
        const target = this.zoomHeld ? SCOPE_FOV : this.fovBase + kick;

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

    private reset(): void {
        this.axes.clear();
        this.lifts.clear();
        this.sprints.clear();
        this.lookRate = { x: 0, y: 0 };
        this.vel = { x: 0, y: 0, z: 0 };
        this.zoomHeld = false;
        this.moved = 0;
    }
}
