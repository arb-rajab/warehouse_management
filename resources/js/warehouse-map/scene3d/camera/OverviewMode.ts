import { LAYOUT } from '../../domain/layout';
import type { Intent } from '../../input/Intent';
import { clamp } from '../../utils/math';
import { forwardVector } from './types';
import type { CameraContext, CameraMode, Pose } from './types';

export interface Orbit {
    /** The point on the floor the camera circles. */
    px: number;
    pz: number;
    yaw: number;
    pitch: number;
    dist: number;
    fov: number;
}

const PAN_KEY_SPEED = 700;
const MIN_DISTANCE = 9;

/** Orbit around the warehouse: left drag orbits, right drag or WASD pans, the wheel zooms. */
export class OverviewMode implements CameraMode {
    readonly id = 'overview' as const;
    private ctx!: CameraContext;
    private orbit: Orbit;
    private minDist = MIN_DISTANCE;
    private maxDist = 100;
    private fitDist: number | null = null;
    private forward = 0;
    private strafe = 0;
    private readonly held = new Map<
        string,
        { forward: number; strafe: number }
    >();

    constructor(initial: Orbit) {
        this.orbit = { ...initial };
    }

    enter(ctx: CameraContext): void {
        this.ctx = ctx;
        this.held.clear();
        this.forward = this.strafe = 0;
        this.ctx.apply(this.pose());
    }

    exit(): void {
        this.held.clear();
        this.forward = this.strafe = 0;
    }

    get distance(): number {
        return this.orbit.dist;
    }

    snapshot(): Orbit {
        return { ...this.orbit };
    }

    /** Replaces the orbit (returning from a walk, flying in on a slot). */
    setOrbit(orbit: Orbit): void {
        this.orbit = { ...orbit };
        this.clampDistance();
    }

    /** A default view that fits the whole warehouse. */
    fitted(centerX: number, centerZ: number): Orbit {
        return {
            px: centerX,
            pz: centerZ,
            yaw: 0,
            pitch: -0.72,
            dist: this.fitDist ?? this.maxDist / 1.5,
            fov: LAYOUT.fovOver,
        };
    }

    /**
     * Keeps the "whole warehouse" framing when the window changes shape,
     * unless the operator has zoomed away from it.
     */
    setFit(fit: number): void {
        const wasFit =
            this.fitDist === null ||
            Math.abs(this.orbit.dist - this.fitDist) < 0.01;

        this.fitDist = fit;
        this.minDist = MIN_DISTANCE;
        this.maxDist = fit * 1.5;

        if (wasFit) {
            this.orbit.dist = fit;
        }

        this.clampDistance();
    }

    poseOf(orbit: Orbit = this.orbit): Pose {
        const f = forwardVector(orbit.yaw, orbit.pitch);

        return {
            x: orbit.px - f.x * orbit.dist,
            y: -f.y * orbit.dist,
            z: orbit.pz - f.z * orbit.dist,
            yaw: orbit.yaw,
            pitch: orbit.pitch,
            fov: orbit.fov,
        };
    }

    pose(): Pose {
        return this.poseOf();
    }

    handle(intent: Intent): boolean {
        switch (intent.type) {
            case 'move':
                this.held.set(intent.source, {
                    forward: intent.forward,
                    strafe: intent.strafe,
                });
                this.sumHeld();

                return true;
            case 'drag':
                if (intent.secondary) {
                    this.pan(intent.dx, intent.dy);
                } else {
                    this.orbitBy(intent.dx, intent.dy);
                }

                return true;
            case 'pinch':
                this.zoom(intent.ratio);
                this.pan(intent.dx, intent.dy);

                return true;
            case 'wheel':
                this.zoom(Math.exp(intent.delta * 0.0012));

                return true;
            case 'zoomStep':
                this.zoom(intent.factor);

                return true;
            default:
                return false;
        }
    }

    update(dt: number): boolean {
        if (!this.forward && !this.strafe) {
            return false;
        }

        this.pan(
            -this.strafe * dt * PAN_KEY_SPEED,
            this.forward * dt * PAN_KEY_SPEED,
        );

        return true;
    }

    private sumHeld(): void {
        this.forward = 0;
        this.strafe = 0;

        for (const axes of this.held.values()) {
            this.forward += axes.forward;
            this.strafe += axes.strafe;
        }

        this.forward = clamp(this.forward, -1, 1);
        this.strafe = clamp(this.strafe, -1, 1);
    }

    private orbitBy(dx: number, dy: number): void {
        const o = this.orbit;
        const sens = this.ctx.settings().sens;

        o.yaw -= dx * 0.0052 * sens;
        o.pitch = clamp(o.pitch - dy * 0.0045 * sens, -1.45, -0.12);
        this.ctx.apply(this.pose());
    }

    /**
     * The spot of floor under the finger stays under it: screen px to metres at
     * the pivot's distance, and a flatter view covers more ground per vertical px.
     */
    private pan(dx: number, dy: number): void {
        const o = this.orbit;
        const metresPerPx =
            (o.dist * 2 * Math.tan((o.fov * Math.PI) / 360)) /
            Math.max(1, this.ctx.viewHeight());
        const k = metresPerPx / Math.max(0.35, Math.sin(-o.pitch));
        const f = [Math.sin(o.yaw), Math.cos(o.yaw)];
        const r = [-Math.cos(o.yaw), Math.sin(o.yaw)];
        const b = this.ctx.geometry.bounds;

        o.px = clamp(
            o.px - r[0] * dx * metresPerPx + f[0] * dy * k,
            b.minX,
            b.maxX,
        );
        o.pz = clamp(
            o.pz - r[1] * dx * metresPerPx + f[1] * dy * k,
            b.minZ,
            b.maxZ,
        );
        this.ctx.apply(this.pose());
    }

    private zoom(factor: number): void {
        this.orbit.dist *= factor;
        this.clampDistance();
        this.ctx.apply(this.pose());
    }

    private clampDistance(): void {
        this.orbit.dist = clamp(this.orbit.dist, this.minDist, this.maxDist);
    }
}
