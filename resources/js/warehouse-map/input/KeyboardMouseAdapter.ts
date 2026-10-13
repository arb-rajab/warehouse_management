import { View } from '../core/View';
import { isTypingTarget } from '../utils/dom';
import { clamp } from '../utils/math';
import { IntentEmitter } from './Intent';
import type {
    InputContext,
    Intent,
    IntentSource,
    PointerCapture,
    PointerKind,
} from './Intent';

const WALK_KEYS: Record<string, [number, number]> = {
    KeyW: [1, 0],
    ArrowUp: [1, 0],
    KeyS: [-1, 0],
    ArrowDown: [-1, 0],
    KeyD: [0, 1],
    ArrowRight: [0, 1],
    KeyA: [0, -1],
    ArrowLeft: [0, -1],
};
const LIFT_UP = new Set(['Space', 'KeyE']);
const LIFT_DOWN = new Set(['KeyZ', 'KeyC', 'KeyQ']);
const SHIFT = new Set(['ShiftLeft', 'ShiftRight']);
const HELD_KEYS = new Set([
    ...Object.keys(WALK_KEYS),
    ...LIFT_UP,
    ...LIFT_DOWN,
    ...SHIFT,
]);
const PREVENT_DEFAULT = new Set([
    'Space',
    'ArrowUp',
    'ArrowDown',
    'ArrowLeft',
    'ArrowRight',
    'PageUp',
    'PageDown',
]);

const TAP_MAX_MOVE = 7;
const TAP_MAX_MS = 450;
const LOOK_RATE = { captured: 0.0022 };

interface DragState {
    x: number;
    y: number;
    startedAt: number;
    moved: number;
    secondary: boolean;
    pointer: PointerKind;
}

/**
 * Desktop input: keys, mouse and pointer lock. The click on the aisle chip is
 * the user gesture that lets the browser capture the mouse, after which just
 * moving the mouse looks around.
 */
export class KeyboardMouseAdapter extends View implements PointerCapture {
    private readonly emitter = new IntentEmitter();
    private readonly held = new Set<string>();
    private drag: DragState | null = null;
    private lastPointerAt = 0;
    private lastAxes = '0|0';
    private lastLift = 0;
    private lastSprint = false;

    constructor(
        private readonly canvas: HTMLCanvasElement,
        private readonly doc: Document,
        private readonly context: InputContext,
        private readonly now: () => number = () => performance.now(),
    ) {
        super();
    }

    onIntent(handler: Parameters<IntentSource['onIntent']>[0]): () => void {
        return this.emitter.onIntent(handler);
    }

    get captured(): boolean {
        return this.doc.pointerLockElement === this.canvas;
    }

    /** When the mouse last moved over the scene (guided mode describes the view after a rest). */
    get lastPointerMoveAt(): number {
        return this.lastPointerAt;
    }

    request(): void {
        if (this.captured) {
            return;
        }

        try {
            const result = this.canvas.requestPointerLock() as unknown;

            if (result instanceof Promise) {
                result.catch(() => {
                    /* not allowed right now: the "click to look around" notice covers it */
                });
            }
        } catch {
            /* same */
        }
    }

    release(): void {
        if (this.doc.pointerLockElement) {
            this.doc.exitPointerLock();
        }
    }

    mount(): void {
        const cv = this.canvas;

        this.on(cv, 'contextmenu', (e: Event) => e.preventDefault());
        this.on(cv, 'pointerdown', (e: PointerEvent) => this.onPointerDown(e));
        this.on(cv, 'pointermove', (e: PointerEvent) => this.onPointerMove(e));
        this.on(cv, 'pointerup', (e: PointerEvent) => this.onPointerEnd(e));
        this.on(cv, 'pointercancel', (e: PointerEvent) => this.onPointerEnd(e));
        this.on(
            cv,
            'wheel',
            (e: WheelEvent) => {
                e.preventDefault();
                this.emit({ type: 'wheel', delta: e.deltaY });
            },
            { passive: false },
        );
        this.on(this.doc, 'mousemove', (e: MouseEvent) =>
            this.onLockedMouseMove(e),
        );
        this.on(this.doc, 'mousedown', (e: MouseEvent) =>
            this.onLockedMouseDown(e),
        );
        this.on(this.doc, 'pointerlockchange', () =>
            this.emit({ type: 'captureChange', captured: this.captured }),
        );
        this.on(window, 'keydown', (e: KeyboardEvent) => this.onKeyDown(e));
        this.on(window, 'keyup', (e: KeyboardEvent) => this.onKeyUp(e));
        this.on(window, 'blur', () => this.releaseAll());
    }

    protected onDispose(): void {
        this.release();
    }

    private emit(intent: Intent): void {
        this.emitter.emit(intent);
    }

    /* ---------- pointer ---------- */

    private onPointerDown(e: PointerEvent): void {
        if (e.pointerType === 'touch' || this.context.mode() === 'fly') {
            return;
        }

        if (e.button === 2 && this.context.mode() === 'walk') {
            this.emit({ type: 'zoomLens', on: true });

            return;
        }

        try {
            this.canvas.setPointerCapture(e.pointerId);
        } catch {
            /* a locked pointer cannot be captured: the mousedown handler covers that case */
        }

        this.canvas.classList.add('is-dragging');
        this.drag = {
            x: e.clientX,
            y: e.clientY,
            startedAt: this.now(),
            moved: 0,
            secondary: e.button === 2 || e.button === 1 || e.shiftKey,
            pointer: e.pointerType as PointerKind,
        };
        this.canvas.focus({ preventScroll: true });
    }

    private onPointerMove(e: PointerEvent): void {
        if (e.pointerType === 'touch') {
            return;
        }

        this.lastPointerAt = this.now();

        if (!this.drag) {
            if (!this.captured) {
                this.emit({ type: 'hover', x: e.clientX, y: e.clientY });
            }

            return;
        }

        const dx = e.clientX - this.drag.x;
        const dy = e.clientY - this.drag.y;

        this.drag.x = e.clientX;
        this.drag.y = e.clientY;
        this.drag.moved += Math.abs(dx) + Math.abs(dy);
        this.emit({
            type: 'drag',
            dx,
            dy,
            secondary: this.drag.secondary,
            pointer: this.drag.pointer,
        });
    }

    private onPointerEnd(e: PointerEvent): void {
        if (e.pointerType === 'touch') {
            return;
        }

        this.canvas.classList.remove('is-dragging');

        if (e.button === 2 && e.type === 'pointerup') {
            this.emit({ type: 'zoomLens', on: false });
        }

        const drag = this.drag;

        if (!drag) {
            return;
        }

        this.drag = null;

        const tapped =
            drag.moved < TAP_MAX_MOVE &&
            this.now() - drag.startedAt < TAP_MAX_MS &&
            e.type === 'pointerup';

        if (tapped) {
            this.emit({
                type: 'tap',
                x: e.clientX,
                y: e.clientY,
                pointer: drag.pointer,
            });
        } else {
            this.emit({ type: 'dragEnd', moved: drag.moved >= TAP_MAX_MOVE });
        }
    }

    private onLockedMouseMove(e: MouseEvent): void {
        if (!this.captured) {
            return;
        }

        this.emit({
            type: 'look',
            dx: clamp(e.movementX, -120, 120),
            dy: clamp(e.movementY, -120, 120),
            rate: LOOK_RATE.captured,
        });
    }

    /** A click while the mouse is captured opens whatever is under the crosshair. */
    private onLockedMouseDown(e: MouseEvent): void {
        if (e.button === 0 && this.captured) {
            this.emit({ type: 'inspect' });
        }
    }

    /* ---------- keys ---------- */

    private onKeyDown(e: KeyboardEvent): void {
        if (isTypingTarget(e.target) || !this.context.isSceneInteractive()) {
            return;
        }

        if (
            e.ctrlKey ||
            e.metaKey ||
            e.altKey ||
            this.context.mode() === 'fly'
        ) {
            return;
        }

        const walking = this.context.mode() === 'walk';
        const guided = walking && this.context.nav() === 'guided';

        if (this.shortcut(e, walking)) {
            return;
        }

        if (guided) {
            this.guidedKey(e);
        } else if (!walking && (e.code === 'Equal' || e.code === 'NumpadAdd')) {
            this.emit({ type: 'zoomStep', factor: 0.85 });
        } else if (
            !walking &&
            (e.code === 'Minus' || e.code === 'NumpadSubtract')
        ) {
            this.emit({ type: 'zoomStep', factor: 1.18 });
        } else if (HELD_KEYS.has(e.code)) {
            this.held.add(e.code);
            this.publishHeld();
        }

        if (PREVENT_DEFAULT.has(e.code)) {
            e.preventDefault();
        }
    }

    /** Keys that mean the same thing everywhere in 3D. Returns true when it handled the key. */
    private shortcut(e: KeyboardEvent, walking: boolean): boolean {
        if (e.code === 'KeyO' && walking) {
            this.emit({ type: 'goOverview' });

            return true;
        }

        if (e.code === 'KeyG' && walking) {
            this.emit({ type: 'toggleNav' });

            return true;
        }

        if (e.code === 'KeyM') {
            this.emit({ type: 'toggleMinimap' });
        }

        if (e.code === 'KeyH') {
            this.emit({ type: 'toggleKeys' });
        }

        const digit = /^Digit([0-9])$/.exec(e.code);

        if (digit) {
            const aisle = Number(digit[1]) || 10;

            if (aisle <= this.context.aisleCount()) {
                this.emit({ type: 'aisle', index: aisle - 1 });

                return true;
            }
        }

        if ((e.code === 'KeyF' || e.code === 'Enter') && walking) {
            this.emit({ type: 'inspect' });

            return true;
        }

        return false;
    }

    private guidedKey(e: KeyboardEvent): void {
        const n = e.shiftKey ? 4 : 1;

        switch (e.code) {
            case 'KeyW':
            case 'ArrowUp':
                this.emit({ type: 'step', forward: n, strafe: 0 });
                break;
            case 'KeyS':
            case 'ArrowDown':
                this.emit({ type: 'step', forward: -n, strafe: 0 });
                break;
            case 'KeyA':
            case 'ArrowLeft':
                this.emit({ type: 'step', forward: 0, strafe: -1 });
                break;
            case 'KeyD':
            case 'ArrowRight':
                this.emit({ type: 'step', forward: 0, strafe: 1 });
                break;
            case 'KeyQ':
                this.emit({ type: 'turn', quarters: 1 });
                break;
            case 'KeyE':
                this.emit({ type: 'turn', quarters: -1 });
                break;
            case 'Space':
            case 'PageUp':
                this.emit({ type: 'changeLevel', delta: 1 });
                break;
            case 'KeyZ':
            case 'KeyC':
            case 'PageDown':
                this.emit({ type: 'changeLevel', delta: -1 });
                break;
        }
    }

    private onKeyUp(e: KeyboardEvent): void {
        if (this.held.delete(e.code)) {
            this.publishHeld();
        }
    }

    private releaseAll(): void {
        this.held.clear();
        this.publishHeld();
        this.emit({ type: 'zoomLens', on: false });
    }

    /** Turns the set of held keys into the walking axes, lift and sprint (only when they changed). */
    private publishHeld(): void {
        let forward = 0;
        let strafe = 0;
        let lift = 0;
        let sprint = false;

        for (const code of this.held) {
            const axis = WALK_KEYS[code];

            if (axis) {
                forward += axis[0];
                strafe += axis[1];
            }

            if (LIFT_UP.has(code)) {
                lift += 1;
            }

            if (LIFT_DOWN.has(code)) {
                lift -= 1;
            }

            sprint ||= SHIFT.has(code);
        }

        forward = clamp(forward, -1, 1);
        strafe = clamp(strafe, -1, 1);
        lift = clamp(lift, -1, 1);

        const axes = `${forward}|${strafe}`;

        if (axes !== this.lastAxes) {
            this.lastAxes = axes;
            this.emit({ type: 'move', source: 'keyboard', forward, strafe });
        }

        if (lift !== this.lastLift) {
            this.lastLift = lift;
            this.emit({ type: 'lift', source: 'keyboard', value: lift });
        }

        if (sprint !== this.lastSprint) {
            this.lastSprint = sprint;
            this.emit({ type: 'sprint', source: 'keyboard', on: sprint });
        }
    }
}
