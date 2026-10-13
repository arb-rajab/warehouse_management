import { THRESHOLDS } from '../config';
import { View } from './View';

/**
 * The warehouse changes all day while the page stays open. When the tab comes
 * back into view (after a while away) it revalidates the data: a 304 costs
 * almost nothing and changes nothing on screen.
 */
export class Refetcher extends View {
    private lastRun: number;
    private running = false;

    constructor(
        private readonly doc: Document,
        private readonly refresh: () => Promise<void>,
        private readonly now: () => number = Date.now,
        private readonly minIntervalMs: number = THRESHOLDS.refetchMinIntervalMs,
    ) {
        super();
        this.lastRun = this.now();
    }

    mount(): void {
        this.on(this.doc, 'visibilitychange', () => {
            if (this.doc.visibilityState === 'visible') {
                void this.maybeRefresh();
            }
        });
        this.on(window, 'focus', () => void this.maybeRefresh());
    }

    /** The data was just loaded elsewhere. */
    markFresh(): void {
        this.lastRun = this.now();
    }

    async maybeRefresh(): Promise<void> {
        if (
            this.running ||
            this.isDisposed ||
            this.now() - this.lastRun < this.minIntervalMs
        ) {
            return;
        }

        this.running = true;
        this.lastRun = this.now();

        try {
            await this.refresh();
        } catch {
            /* keep what is on screen; the next return to the tab tries again */
        } finally {
            this.running = false;
        }
    }
}
