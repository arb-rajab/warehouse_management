import { parseHash, slotHash } from '../domain/SlotCode';
import type { HashTarget } from '../domain/SlotCode';
import type { ViewKind } from '../state/state';
import { View } from './View';

/**
 * Keeps the page fragment in step with the view (`#2d` / `#3d`) and opens deep
 * links (`#slot=B5-2` shows the 3D view in front of that slot), also when the
 * fragment changes while the page is open. The history state is preserved so
 * the panel's own navigation keeps working.
 */
export class HashSync extends View {
    constructor(
        private readonly win: Window,
        private readonly onTarget: (target: HashTarget) => void,
    ) {
        super();
    }

    mount(): void {
        this.on(this.win, 'hashchange', () => this.applyCurrent());
    }

    /** Opens whatever the current fragment points at. Returns whether it pointed at anything. */
    applyCurrent(): boolean {
        const target = parseHash(this.win.location.hash);

        if (!target) {
            return false;
        }

        this.onTarget(target);

        return true;
    }

    writeView(view: ViewKind): void {
        this.write(`#${view}`);
    }

    private write(hash: string): void {
        if (this.win.location.hash === hash) {
            return;
        }

        this.win.history.replaceState(this.win.history.state, '', hash);
    }

    static slotLink(
        win: Window,
        ref: { row: string; col: number; level: number },
    ): string {
        return win.location.href.split('#')[0] + slotHash(ref);
    }
}
