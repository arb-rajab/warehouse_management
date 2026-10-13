import { View } from '../core/View';
import { pinchState } from './PlanLayout';
import type { PinchState, Pointer2D } from './PlanLayout';

export interface PlanGestureHandlers {
    onPan(dx: number, dy: number): void;
    onZoom(factor: number, cx: number, cy: number): void;
    /** A short still press that landed on a tile. */
    onTapTile(tile: HTMLElement): void;
    onFit(): void;
    /** A finger or the mouse took (true) or let go of (false) the plan. */
    onGrab(grabbed: boolean): void;
}

const TAP_MAX_MOVE = 7;

/** Drag = pan, wheel = zoom, two fingers = pinch, a short still press = a tap. */
export class PlanGestures extends View {
    private readonly pointers = new Map<number, Pointer2D>();
    private moved = 0;
    private last: PinchState | null = null;

    constructor(
        private readonly stage: HTMLElement,
        private readonly handlers: PlanGestureHandlers,
        private readonly hitTest: (x: number, y: number) => Element | null = (
            x,
            y,
        ) => document.elementFromPoint?.(x, y) ?? null,
    ) {
        super();
    }

    mount(): void {
        const stage = this.stage;

        this.on(stage, 'pointerdown', (e: PointerEvent) => this.down(e));
        this.on(stage, 'pointermove', (e: PointerEvent) => this.move(e));
        this.on(stage, 'pointerup', (e: PointerEvent) => this.up(e));
        this.on(stage, 'pointercancel', (e: PointerEvent) => this.up(e));
        this.on(
            stage,
            'wheel',
            (e: WheelEvent) => {
                e.preventDefault();
                this.handlers.onZoom(
                    Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0015)),
                    e.clientX,
                    e.clientY,
                );
            },
            { passive: false },
        );
        this.on(stage, 'dblclick', (e: MouseEvent) => {
            if (!(e.target as Element | null)?.closest?.('.wms-tile')) {
                this.handlers.onFit();
            }
        });
    }

    private down(e: PointerEvent): void {
        if ((e.target as Element | null)?.closest?.('.wms-hud')) {
            return;
        }

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch {
            /* a synthetic pointer cannot be captured */
        }

        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        this.moved = 0;
        this.last = pinchState([...this.pointers.values()]);
        this.handlers.onGrab(true);
    }

    private move(e: PointerEvent): void {
        const p = this.pointers.get(e.pointerId);

        if (!p) {
            return;
        }

        const dx = e.clientX - p.x;
        const dy = e.clientY - p.y;

        p.x = e.clientX;
        p.y = e.clientY;
        this.moved += Math.abs(dx) + Math.abs(dy);

        const current = pinchState([...this.pointers.values()]);

        if (this.pointers.size >= 2 && this.last && current) {
            this.handlers.onPan(
                current.cx - this.last.cx,
                current.cy - this.last.cy,
            );
            this.handlers.onZoom(
                current.distance / this.last.distance,
                current.cx,
                current.cy,
            );
        } else {
            this.handlers.onPan(dx, dy);
        }

        this.last = current;
    }

    private up(e: PointerEvent): void {
        if (!this.pointers.has(e.pointerId)) {
            return;
        }

        this.pointers.delete(e.pointerId);
        this.last = pinchState([...this.pointers.values()]);

        if (this.pointers.size > 0) {
            return;
        }

        this.handlers.onGrab(false);

        if (this.moved < TAP_MAX_MOVE && e.type === 'pointerup') {
            const tile = this.hitTest(e.clientX, e.clientY)?.closest?.(
                '.wms-tile',
            );

            if (tile instanceof HTMLElement) {
                this.handlers.onTapTile(tile);
            }
        }
    }
}
