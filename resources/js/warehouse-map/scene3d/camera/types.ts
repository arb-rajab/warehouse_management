import type { UserSettings } from '../../config';
import type { WarehouseGeometry } from '../../domain/geometry';
import type { Intent } from '../../input/Intent';
import type { WallCollider } from '../collision/WallCollider';

/** Where the eye is and which way it looks. `yaw` 0 looks along +z, `pitch` > 0 looks up. */
export interface Pose {
    x: number;
    y: number;
    z: number;
    yaw: number;
    pitch: number;
    fov: number;
}

export type ModeId = 'overview' | 'free' | 'guided';

/** What a camera mode may use of the scene. */
export interface CameraContext {
    readonly geometry: WarehouseGeometry;
    readonly collider: WallCollider;
    settings(): UserSettings;
    /** Puts the camera at a pose; `bob` lifts the eye a little while walking. */
    apply(pose: Pose, bob?: number): void;
    /** Height of the canvas in CSS px (screen px to metres when panning). */
    viewHeight(): number;
    /** A short vibration on touch devices. */
    buzz(ms: number): void;
}

/**
 * One way of moving the camera. A mode reads only intents, so a new one is a
 * new class registered in `main.ts`; `CameraRig` is never edited for it.
 */
export interface CameraMode {
    readonly id: ModeId;
    enter(ctx: CameraContext): void;
    /** Advances the mode by a frame. Returns true when the camera moved (a render is due). */
    update(dt: number, now: number): boolean;
    /** Returns true when the intent was consumed. */
    handle(intent: Intent): boolean;
    exit(): void;
    pose(): Pose;
}

export function forwardVector(
    yaw: number,
    pitch: number,
): { x: number; y: number; z: number } {
    return {
        x: Math.sin(yaw) * Math.cos(pitch),
        y: Math.sin(pitch),
        z: Math.cos(yaw) * Math.cos(pitch),
    };
}
