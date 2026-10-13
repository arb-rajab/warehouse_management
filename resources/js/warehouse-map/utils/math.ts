export const clamp = (value: number, min: number, max: number): number =>
    Math.max(min, Math.min(max, value));

export const lerp = (from: number, to: number, t: number): number =>
    from + (to - from) * t;

/** Cubic ease in/out: slow, quick, slow. `t` runs 0..1. */
export const easeInOutCubic = (t: number): number =>
    t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

/** The shortest signed way round from one angle to another (radians). */
export const angleDelta = (from: number, to: number): number => {
    const delta = to - from;

    return Math.atan2(Math.sin(delta), Math.cos(delta));
};

/** Interpolates between two angles along the short way round. */
export const lerpAngle = (from: number, to: number, t: number): number =>
    from + angleDelta(from, to) * t;
