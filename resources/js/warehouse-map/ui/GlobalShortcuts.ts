import { View } from '../core/View';
import type { PlanControls } from '../plan2d/PlanView';
import { isTypingTarget } from '../utils/dom';

export interface ShortcutIntents {
    /** Closes the overlay on top; false when none was open. */
    closeTopOverlay(): boolean;
    toggleFolded(): void;
    toggleHelp(): void;
    focusSearch(): void;
    resetPlanOrientation(): void;
    view(): '2d' | '3d';
    isOverlayOpen(): boolean;
}

/** The keys that work on every view: Esc, / (search), T (fold the bars), ? (help), and the 2D plan's 0, R, + and -. */
export class GlobalShortcuts extends View {
    constructor(
        private readonly intents: ShortcutIntents,
        private readonly plan: PlanControls,
    ) {
        super();
    }

    mount(): void {
        this.on(window, 'keydown', (e: KeyboardEvent) => this.onKey(e));
    }

    private onKey(e: KeyboardEvent): void {
        if (isTypingTarget(e.target)) {
            if (e.key === 'Escape' && e.target instanceof HTMLElement) {
                e.target.blur();
            }

            return;
        }

        // never hijack a browser shortcut (Ctrl+W closes the tab, so Ctrl is not a game key)
        if (e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }

        if (e.key === 'Escape') {
            this.intents.closeTopOverlay();

            return;
        }

        if (e.key === '/') {
            e.preventDefault();
            this.intents.focusSearch();

            return;
        }

        if (e.code === 'KeyT') {
            this.intents.toggleFolded();
        }

        if (e.key === '?') {
            e.preventDefault();
            this.intents.toggleHelp();

            return;
        }

        if (this.intents.view() !== '2d' || this.intents.isOverlayOpen()) {
            return;
        }

        if (e.key === '0') {
            this.intents.resetPlanOrientation();
            this.plan.fit(true);
        } else if (e.code === 'KeyR') {
            this.plan.rotate();
        } else if (e.key === '+' || e.key === '=') {
            this.plan.zoomBy(1.25, true);
        } else if (e.key === '-') {
            this.plan.zoomBy(0.8, true);
        }
    }
}
