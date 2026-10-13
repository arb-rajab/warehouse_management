import type { MatchIndex, Slot, Warehouse } from '../domain/types';
import type { Intent } from '../input/Intent';
import type { NavMode } from '../state/state';
import type { SceneView } from './SceneView';

/** What the rest of the page asks of the 3D scene. */
export interface ScenePort {
    readonly isBuilt: boolean;
    show(): void;
    hide(): void;
    focusSlot(slot: Slot): void;
    enterAisle(aisle: number): void;
    goOverview(): void;
    setNav(nav: NavMode): void;
    handleIntent(intent: Intent): void;
    setFilter(match: MatchIndex, floor: number | null): void;
    setTarget(slot: Slot | null): void;
    setWarehouse(warehouse: Warehouse): void;
    applySettings(): void;
    onLocale(): void;
    onInputKind(): void;
    resize(): void;
}

/**
 * Builds the 3D scene only when it is needed. Building takes about half a
 * second, so the page does it while the browser is idle (or as soon as the
 * pointer nears the 3D button), and switching to 3D is then instant. State the
 * page sets before the scene exists (the filter, the target) is kept and
 * applied the moment it is built.
 */
export class LazySceneView implements ScenePort {
    private scene: SceneView | null = null;
    private filter: { match: MatchIndex; floor: number | null } | null = null;
    private target: Slot | null = null;
    private warehouse: Warehouse | null = null;
    private idleHandle: number | ReturnType<typeof setTimeout> | undefined;
    private disposed = false;

    constructor(
        private readonly create: (warehouse: Warehouse) => SceneView,
        private readonly onBuilt: (scene: SceneView) => void,
    ) {}

    get isBuilt(): boolean {
        return this.scene !== null;
    }

    get built(): SceneView | null {
        return this.scene;
    }

    /** Builds the scene now (if there is a warehouse to build it from). */
    ensure(): SceneView | null {
        if (this.scene || this.disposed || !this.warehouse) {
            return this.scene;
        }

        this.scene = this.create(this.warehouse);
        this.scene.mount();

        if (this.filter) {
            this.scene.setFilter(this.filter.match, this.filter.floor);
        }

        if (this.target) {
            this.scene.setTarget(this.target);
        }

        this.onBuilt(this.scene);

        return this.scene;
    }

    /** Builds the scene in the browser's idle time. */
    prepareWhenIdle(timeoutMs = 4000): void {
        if (this.scene || this.disposed) {
            return;
        }

        const run = () => {
            this.idleHandle = undefined;
            this.ensure();
        };

        if (typeof window.requestIdleCallback === 'function') {
            this.idleHandle = window.requestIdleCallback(run, {
                timeout: timeoutMs,
            });
        } else {
            this.idleHandle = setTimeout(run, 200);
        }
    }

    dispose(): void {
        this.disposed = true;

        if (this.idleHandle !== undefined) {
            if (
                typeof window.cancelIdleCallback === 'function' &&
                typeof this.idleHandle === 'number'
            ) {
                window.cancelIdleCallback(this.idleHandle);
            } else {
                clearTimeout(this.idleHandle as ReturnType<typeof setTimeout>);
            }
        }

        this.scene?.dispose();
        this.scene = null;
    }

    show(): void {
        this.ensure()?.show();
    }

    hide(): void {
        this.scene?.hide();
    }

    focusSlot(slot: Slot): void {
        this.ensure()?.focusSlot(slot);
    }

    enterAisle(aisle: number): void {
        this.ensure()?.enterAisle(aisle);
    }

    goOverview(): void {
        this.scene?.goOverview();
    }

    setNav(nav: NavMode): void {
        this.ensure()?.setNav(nav);
    }

    handleIntent(intent: Intent): void {
        this.scene?.handleIntent(intent);
    }

    setFilter(match: MatchIndex, floor: number | null): void {
        this.filter = { match, floor };
        this.scene?.setFilter(match, floor);
    }

    setTarget(slot: Slot | null): void {
        this.target = slot;
        this.scene?.setTarget(slot);
    }

    setWarehouse(warehouse: Warehouse): void {
        this.warehouse = warehouse;
        this.scene?.setWarehouse(warehouse);
    }

    applySettings(): void {
        this.scene?.applySettings();
    }

    onLocale(): void {
        this.scene?.onLocale();
    }

    onInputKind(): void {
        this.scene?.onInputKind();
    }

    resize(): void {
        this.scene?.resize();
    }
}
