import { View } from '../core/View';
import { IntentEmitter } from './Intent';
import type { InputContext, Intent, IntentSource } from './Intent';

const TAP_MAX_MOVE = 7;
const TAP_MAX_MS = 450;
const STICK_RADIUS = 56;
const STICK_KNOB_TRAVEL = 44;
const PAD_REPEAT_MS = 330;
/** A touch starting in this left share of the screen holds the walking joystick. */
const STICK_ZONE = 0.45;

export interface TouchElements {
    canvas: HTMLElement;
    stick: HTMLElement;
    liftUp: HTMLElement;
    liftDown: HTMLElement;
    /** The guided pad buttons, each tagged with the action it fires. */
    padButtons: Array<{ element: HTMLElement; action: PadAction }>;
}

export type PadAction = 'up' | 'down' | 'tl' | 'tr' | 'fw' | 'bk' | 'sl' | 'sr';

const PAD_INTENTS: Record<PadAction, Intent> = {
    up: { type: 'changeLevel', delta: 1 },
    down: { type: 'changeLevel', delta: -1 },
    tl: { type: 'turn', quarters: 1 },
    tr: { type: 'turn', quarters: -1 },
    fw: { type: 'step', forward: 1, strafe: 0 },
    bk: { type: 'step', forward: -1, strafe: 0 },
    sl: { type: 'step', forward: 0, strafe: -1 },
    sr: { type: 'step', forward: 0, strafe: 1 },
};

interface TrackedPointer {
    x: number;
    y: number;
    startX: number;
    startY: number;
    startedAt: number;
    moved: number;
    role: 'stick' | 'look';
}

/**
 * Touch input: a floating joystick under the left thumb, drag-to-look under
 * the right, lift buttons in free walk and the stepping pad in guided mode.
 */
export class TouchAdapter extends View implements IntentSource {
    private readonly emitter = new IntentEmitter();
    private readonly pointers = new Map<number, TrackedPointer>();
    private readonly repeats = new Set<ReturnType<typeof setInterval>>();
    private lastPinch: { distance: number; cx: number; cy: number } | null =
        null;

    constructor(
        private readonly els: TouchElements,
        private readonly context: InputContext,
        private readonly now: () => number = () => performance.now(),
    ) {
        super();
    }

    onIntent(handler: Parameters<IntentSource['onIntent']>[0]): () => void {
        return this.emitter.onIntent(handler);
    }

    mount(): void {
        const cv = this.els.canvas;

        this.on(cv, 'pointerdown', (e: PointerEvent) => this.onDown(e));
        this.on(cv, 'pointermove', (e: PointerEvent) => this.onMove(e));
        this.on(cv, 'pointerup', (e: PointerEvent) => this.onEnd(e));
        this.on(cv, 'pointercancel', (e: PointerEvent) => this.onEnd(e));
        this.bindHold(this.els.liftUp, 1);
        this.bindHold(this.els.liftDown, -1);

        for (const { element, action } of this.els.padButtons) {
            this.bindPad(element, action);
        }
    }

    protected onDispose(): void {
        this.repeats.forEach((timer) => clearInterval(timer));
        this.repeats.clear();
    }

    private emit(intent: Intent): void {
        this.emitter.emit(intent);
    }

    private onDown(e: PointerEvent): void {
        if (e.pointerType !== 'touch' || this.context.mode() === 'fly') {
            return;
        }

        try {
            this.els.canvas.setPointerCapture(e.pointerId);
        } catch {
            /* ignore: a synthetic event has no active pointer to capture */
        }

        const width =
            this.els.canvas.getBoundingClientRect().width || window.innerWidth;
        const canStick =
            this.context.mode() === 'walk' &&
            this.context.nav() === 'free' &&
            e.clientX < width * STICK_ZONE;

        this.pointers.set(e.pointerId, {
            x: e.clientX,
            y: e.clientY,
            startX: e.clientX,
            startY: e.clientY,
            startedAt: this.now(),
            moved: 0,
            role: canStick ? 'stick' : 'look',
        });

        if (canStick) {
            this.placeStick(e.clientX, e.clientY);
        }

        this.lastPinch = this.pinchState();
        this.els.canvas.focus({ preventScroll: true });
    }

    private onMove(e: PointerEvent): void {
        const p = this.pointers.get(e.pointerId);

        if (!p) {
            return;
        }

        const dx = e.clientX - p.x;
        const dy = e.clientY - p.y;

        p.x = e.clientX;
        p.y = e.clientY;
        p.moved += Math.abs(dx) + Math.abs(dy);

        if (p.role === 'stick') {
            this.moveStick(p);

            return;
        }

        const looking = [...this.pointers.values()].filter(
            (q) => q.role === 'look',
        );

        if (looking.length >= 2) {
            this.pinch(looking);
        } else {
            this.emit({
                type: 'drag',
                dx,
                dy,
                secondary: false,
                pointer: 'touch',
            });
        }
    }

    private onEnd(e: PointerEvent): void {
        const p = this.pointers.get(e.pointerId);

        if (!p) {
            return;
        }

        this.pointers.delete(e.pointerId);
        this.lastPinch = null;

        if (p.role === 'stick') {
            this.emit({ type: 'move', source: 'touch', forward: 0, strafe: 0 });
            this.els.stick.classList.remove('is-active');

            return;
        }

        const tapped =
            p.moved < TAP_MAX_MOVE &&
            this.now() - p.startedAt < TAP_MAX_MS &&
            e.type === 'pointerup';

        if (tapped) {
            this.emit({
                type: 'tap',
                x: e.clientX,
                y: e.clientY,
                pointer: 'touch',
            });
        } else {
            this.emit({ type: 'dragEnd', moved: p.moved >= TAP_MAX_MOVE });
        }
    }

    private placeStick(x: number, y: number): void {
        const stick = this.els.stick;

        stick.style.setProperty('--wms-stick-left', `${x}px`);
        stick.style.setProperty('--wms-stick-top', `${y}px`);
        stick.style.setProperty('--wms-knob-x', '0px');
        stick.style.setProperty('--wms-knob-y', '0px');
        stick.classList.add('is-active');
    }

    private moveStick(p: TrackedPointer): void {
        const vx = p.x - p.startX;
        const vy = p.y - p.startY;
        const magnitude = Math.min(1, Math.hypot(vx, vy) / STICK_RADIUS);
        const angle = Math.atan2(vy, vx);

        this.els.stick.style.setProperty(
            '--wms-knob-x',
            `${Math.cos(angle) * magnitude * STICK_KNOB_TRAVEL}px`,
        );
        this.els.stick.style.setProperty(
            '--wms-knob-y',
            `${Math.sin(angle) * magnitude * STICK_KNOB_TRAVEL}px`,
        );
        this.emit({
            type: 'move',
            source: 'touch',
            forward: -Math.sin(angle) * magnitude,
            strafe: Math.cos(angle) * magnitude,
        });
    }

    private pinchState(): { distance: number; cx: number; cy: number } | null {
        const looking = [...this.pointers.values()].filter(
            (q) => q.role === 'look',
        );

        if (looking.length < 2) {
            return null;
        }

        const [a, b] = looking;

        return {
            distance: Math.hypot(a.x - b.x, a.y - b.y) || 1,
            cx: (a.x + b.x) / 2,
            cy: (a.y + b.y) / 2,
        };
    }

    private pinch(looking: TrackedPointer[]): void {
        const current = this.pinchState();

        if (!current || looking.length < 2) {
            return;
        }

        if (this.lastPinch) {
            this.emit({
                type: 'pinch',
                ratio: this.lastPinch.distance / current.distance,
                dx: current.cx - this.lastPinch.cx,
                dy: current.cy - this.lastPinch.cy,
            });
        }

        this.lastPinch = current;
    }

    /** A held button that raises or lowers you while it is pressed. */
    private bindHold(button: HTMLElement, value: number): void {
        const release = () =>
            this.emit({ type: 'lift', source: 'touch', value: 0 });

        this.on(button, 'pointerdown', (e: PointerEvent) => {
            e.stopPropagation();
            this.emit({ type: 'lift', source: 'touch', value });
        });
        this.on(button, 'pointerup', release);
        this.on(button, 'pointerleave', release);
        this.on(button, 'pointercancel', release);
    }

    /** A press steps once, holding keeps stepping. */
    private bindPad(button: HTMLElement, action: PadAction): void {
        let repeat: ReturnType<typeof setInterval> | undefined;

        const fire = () => this.emit(PAD_INTENTS[action]);
        const stop = () => {
            if (repeat) {
                clearInterval(repeat);
                this.repeats.delete(repeat);
                repeat = undefined;
            }
        };

        this.on(button, 'pointerdown', (e: PointerEvent) => {
            e.preventDefault();
            fire();
            stop();
            repeat = setInterval(fire, PAD_REPEAT_MS);
            this.repeats.add(repeat);
        });
        this.on(button, 'pointerup', stop);
        this.on(button, 'pointerleave', stop);
        this.on(button, 'pointercancel', stop);
    }
}
