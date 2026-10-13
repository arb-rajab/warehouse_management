import type { Intent } from '../../input/Intent';
import { angleDelta, easeInOutCubic, lerp } from '../../utils/math';
import type { CameraContext, CameraMode, ModeId, Pose } from './types';

export type RigState = 'overview' | 'walk' | 'fly';

interface Flight {
    from: Pose;
    to: Pose;
    startedAt: number;
    duration: number;
    done?: () => void;
}

/**
 * Owns the camera and swaps its modes. It knows nothing about any particular
 * mode: it asks the active one to enter, update, handle intents and exit,
 * and runs the eased flights between poses itself, so it is never edited for
 * a new mode.
 */
export class CameraRig {
    private readonly modes = new Map<ModeId, CameraMode>();
    private active: CameraMode | null = null;
    private flight: Flight | null = null;
    private current: Pose | null = null;

    constructor(
        private readonly ctx: CameraContext,
        private readonly onStateChange: (
            state: RigState,
            mode: ModeId | null,
        ) => void = () => {},
        private readonly clock: () => number = () => performance.now(),
    ) {}

    register(mode: CameraMode): void {
        this.modes.set(mode.id, mode);
    }

    get activeId(): ModeId | null {
        return this.active?.id ?? null;
    }

    get isFlying(): boolean {
        return this.flight !== null;
    }

    get state(): RigState {
        if (this.flight) {
            return 'fly';
        }

        return this.active?.id === 'overview' ? 'overview' : 'walk';
    }

    /** Leaves the current mode and enters another (re-entering the same one resets it). */
    activate(id: ModeId): void {
        const next = this.modes.get(id);

        if (!next) {
            throw new Error(`No camera mode registered for "${id}"`);
        }

        this.flight = null;
        this.active?.exit();
        this.active = next;
        next.enter(this.ctx);
        this.onStateChange(this.state, id);
    }

    currentPose(): Pose {
        if (this.flight && this.current) {
            return { ...this.current };
        }

        return { ...(this.active?.pose() as Pose) };
    }

    /** Eases the camera to a pose; intents are ignored on the way. */
    flyTo(
        to: Pose,
        options: { duration?: number; done?: () => void } = {},
    ): void {
        const from = this.currentPose();

        this.current = { ...from };
        this.flight = {
            from,
            to,
            startedAt: this.clock(),
            duration: options.duration ?? 950,
            done: options.done,
        };
        this.onStateChange('fly', this.activeId);
    }

    handle(intent: Intent): boolean {
        if (this.flight || !this.active) {
            return false;
        }

        return this.active.handle(intent);
    }

    /** Returns true when the camera moved this frame. */
    update(dt: number, now: number): boolean {
        if (this.flight) {
            this.tickFlight(now);

            return true;
        }

        return this.active?.update(dt, now) ?? false;
    }

    private tickFlight(now: number): void {
        const f = this.flight!;
        const u = Math.min(1, (now - f.startedAt) / f.duration);
        const e = easeInOutCubic(u);
        const { from: a, to: b } = f;

        this.current = {
            x: lerp(a.x, b.x, e),
            y: lerp(a.y, b.y, e),
            z: lerp(a.z, b.z, e),
            yaw: a.yaw + angleDelta(a.yaw, b.yaw) * e,
            pitch: lerp(a.pitch, b.pitch, e),
            fov: lerp(a.fov, b.fov, e),
        };
        this.ctx.apply(this.current);

        if (u >= 1) {
            this.flight = null;
            f.done?.();
        }
    }
}
