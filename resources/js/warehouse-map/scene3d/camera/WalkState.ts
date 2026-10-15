import { LAYOUT } from '../../domain/layout';
import type { Pose } from './types';

/**
 * The walker, shared by the free and the guided mode so that switching between
 * them (G) keeps you where you stand and which way you look.
 */
export class WalkState {
    pose: Pose = { x: 0, y: LAYOUT.eye, z: -8, yaw: 0, pitch: 0, fov: 62 };
    /** Which shelf height the eye is at (0-based), for guided level changes. */
    eyeIndex = 1;
    /** The small up/down sway of walking. */
    bobY = 0;

    set(pose: Pose): void {
        this.pose = { ...pose };
    }
}
