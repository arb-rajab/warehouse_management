import type { Slot } from '../domain/types';
import type { PlanControls } from '../plan2d/PlanView';
import type { ScenePort } from '../scene3d/LazySceneView';
import type { ViewKind } from '../state/state';

export interface NavigatorDeps {
    view(): ViewKind;
    setView(view: ViewKind): void;
    setTarget(slot: Slot | null): void;
    closeRack(): void;
    scene: ScenePort & { ensure(): unknown };
    plan: PlanControls;
    /** Fonts the 3D labels are drawn with must be loaded before the scene is built. */
    fontsReady(): Promise<unknown>;
    /** The next frame (the 2D plan can only be measured once it is on screen). */
    nextFrame(cb: () => void): void;
    /** Called when the 3D scene could not start (no WebGL). */
    onSceneFailed(error: unknown): void;
    writeHash(view: ViewKind): void;
}

/**
 * Moves the operator between the two views and to a place on the map, keeping
 * the filter, the floor and the selected slot. Views ask it for "show this
 * slot on the map"; they never reach into each other.
 */
export class Navigator {
    private sequence = 0;

    constructor(private readonly deps: NavigatorDeps) {}

    /** Switches view; `then` runs once the new view is showing. */
    setView(view: ViewKind, then?: () => void): void {
        const ticket = ++this.sequence;

        if (view === '2d') {
            this.deps.scene.hide();
            this.deps.setView('2d');
            this.deps.writeHash('2d');
            this.deps.nextFrame(() => {
                if (ticket === this.sequence) {
                    then?.();
                }
            });

            return;
        }

        void this.deps.fontsReady().then(() => {
            if (ticket !== this.sequence) {
                return;
            }

            this.deps.setView('3d');
            this.deps.writeHash('3d');

            try {
                this.deps.scene.ensure();
                this.deps.scene.show();
            } catch (error) {
                this.deps.setView('2d');
                this.deps.writeHash('2d');
                this.deps.onSceneFailed(error);

                return;
            }

            then?.();
        });
    }

    /** "View on map" from the drawer: in whichever view is active. */
    viewOnMap(slot: Slot): void {
        if (this.deps.view() === '3d') {
            this.deps.scene.focusSlot(slot);
        } else {
            this.showInPlan(slot);
        }
    }

    showInPlan(slot: Slot): void {
        this.deps.closeRack();
        this.deps.setTarget(slot);
        this.setView('2d', () => this.deps.plan.reveal(slot));
    }

    showIn3d(slot: Slot): void {
        this.deps.closeRack();
        this.setView('3d', () => this.deps.scene.focusSlot(slot));
    }
}
