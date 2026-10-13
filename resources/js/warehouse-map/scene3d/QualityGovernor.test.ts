import { describe, expect, it } from 'vitest';
import { QualityGovernor } from './QualityGovernor';

/** Feeds frames of a given duration, returns the last non-null decision. */
function run(
    governor: QualityGovernor,
    from: number,
    frames: number,
    frameMs: number,
    ratio: number,
) {
    let now = from;
    let decision: number | null = null;
    let current = ratio;

    for (let i = 0; i < frames; i++) {
        now += frameMs;

        const next = governor.sample(now, true, current);

        if (next !== null) {
            decision = next;
            current = next;
        }
    }

    return { decision, now, current };
}

describe('QualityGovernor', () => {
    it('leaves a smooth page alone at the top resolution', () => {
        const governor = new QualityGovernor({ maxPixelRatio: 2 });

        expect(run(governor, 0, 400, 16, 2).decision).toBeNull();
    });

    it('steps the resolution down when frames keep taking too long', () => {
        const governor = new QualityGovernor({ maxPixelRatio: 2 });
        const { decision } = run(governor, 0, 70, 40, 2);

        expect(decision).toBe(1.75);
    });

    it('never goes below a pixel ratio of 1', () => {
        const governor = new QualityGovernor({ maxPixelRatio: 2 });
        const { current } = run(governor, 0, 4000, 60, 2);

        expect(current).toBe(1);
    });

    it('creeps back up when there is headroom, after the hold', () => {
        const governor = new QualityGovernor({
            maxPixelRatio: 2,
            holdMs: 5000,
        });
        const slow = run(governor, 0, 300, 40, 2);
        const early = run(governor, slow.now, 100, 10, slow.current);

        // the average first has to recover, and the hold keeps it down meanwhile
        expect(early.current).toBeLessThanOrEqual(slow.current);

        const later = run(governor, early.now, 3000, 10, early.current);

        expect(later.current).toBeGreaterThan(early.current);
        expect(later.current).toBeLessThanOrEqual(2);
    });

    it('does not count idle time as slow frames', () => {
        const governor = new QualityGovernor({ maxPixelRatio: 2 });

        governor.sample(0, true, 2);
        governor.sample(10_000, false, 2);
        governor.sample(10_016, true, 2);

        expect(governor.averageFrameMs).toBeLessThan(20);
    });

    it('only decides every interval', () => {
        const governor = new QualityGovernor({
            maxPixelRatio: 2,
            intervalMs: 5000,
        });
        const decisions: number[] = [];
        let now = 0;

        for (let i = 0; i < 300; i++) {
            now += 40;

            const next = governor.sample(now, true, 2);

            if (next !== null) {
                decisions.push(now);
            }
        }

        expect(decisions.length).toBeLessThanOrEqual(3);
    });
});
