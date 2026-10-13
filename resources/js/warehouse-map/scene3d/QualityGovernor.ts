export interface GovernorOptions {
    /** The renderer's ceiling (device pixel ratio, at most 2). */
    maxPixelRatio: number;
    /** Above this average frame time (ms) the resolution steps down. */
    slowMs?: number;
    /** Below this average (ms) it creeps back up. */
    fastMs?: number;
    /** How often it may change anything. */
    intervalMs?: number;
    /** After stepping down, how long before it tries stepping up again. */
    holdMs?: number;
    step?: number;
}

/**
 * Adaptive resolution: if frames keep taking too long the canvas renders at a
 * lower pixel ratio, and creeps back up when there is headroom, so the walk
 * stays smooth on a modest machine. Pure: it only decides; the scene applies.
 */
export class QualityGovernor {
    private lastRender = 0;
    private average = 16.7;
    private lastDecision = 0;
    private noRaiseUntil = 0;

    private readonly slowMs: number;
    private readonly fastMs: number;
    private readonly intervalMs: number;
    private readonly holdMs: number;
    private readonly step: number;

    constructor(private readonly options: GovernorOptions) {
        this.slowMs = options.slowMs ?? 26;
        this.fastMs = options.fastMs ?? 18;
        this.intervalMs = options.intervalMs ?? 2500;
        this.holdMs = options.holdMs ?? 20_000;
        this.step = options.step ?? 0.25;
    }

    get averageFrameMs(): number {
        return this.average;
    }

    /**
     * Feed it every frame. `rendered` says whether the frame drew anything (an
     * idle page must not look slow). Returns the pixel ratio to switch to, or
     * null to leave it alone.
     */
    sample(
        now: number,
        rendered: boolean,
        currentRatio: number,
    ): number | null {
        if (!rendered) {
            this.lastRender = 0;

            return null;
        }

        const delta = now - (this.lastRender || now);

        this.lastRender = now;

        if (delta > 0 && delta < 120) {
            this.average = this.average * 0.92 + delta * 0.08;
        }

        if (now - this.lastDecision < this.intervalMs) {
            return null;
        }

        this.lastDecision = now;

        if (this.average > this.slowMs && currentRatio > 1) {
            this.noRaiseUntil = now + this.holdMs;

            return Math.max(1, currentRatio - this.step);
        }

        if (
            this.average < this.fastMs &&
            currentRatio < this.options.maxPixelRatio &&
            now > this.noRaiseUntil
        ) {
            return Math.min(
                this.options.maxPixelRatio,
                currentRatio + this.step,
            );
        }

        return null;
    }
}
