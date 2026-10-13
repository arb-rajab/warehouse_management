import { IntentEmitter } from './Intent';
import type { Intent, IntentSource } from './Intent';

const DEAD_ZONE = 0.18;

export interface GamepadLike {
    connected: boolean;
    axes: readonly number[];
    buttons: ReadonlyArray<{ pressed: boolean }>;
}

const deadZone = (value: number | undefined): number =>
    Math.abs(value ?? 0) < DEAD_ZONE ? 0 : (value ?? 0);

/**
 * Gamepad input: left stick walks, right stick looks, bumpers lift, left
 * trigger sprints. Polled once per frame by the frame loop, it only emits
 * when a value changed (the look stick, which turns continuously, emits
 * every frame while it is held).
 */
export class GamepadAdapter implements IntentSource {
    private readonly emitter = new IntentEmitter();
    private last = {
        forward: 0,
        strafe: 0,
        lift: 0,
        sprint: false,
        lookX: 0,
        lookY: 0,
    };

    constructor(
        private readonly getPads: () => ReadonlyArray<GamepadLike | null> = () =>
            typeof navigator !== 'undefined' && navigator.getGamepads
                ? Array.from(navigator.getGamepads())
                : [],
    ) {}

    onIntent(handler: Parameters<IntentSource['onIntent']>[0]): () => void {
        return this.emitter.onIntent(handler);
    }

    /** Called every frame while the walk is interactive. */
    poll(): void {
        const pad = this.getPads().find((p) => p?.connected) ?? null;
        const forward = pad ? 0 - deadZone(pad.axes[1]) : 0;
        const strafe = pad ? deadZone(pad.axes[0]) : 0;
        const lift = pad
            ? (pad.buttons[5]?.pressed ? 1 : 0) -
              (pad.buttons[4]?.pressed ? 1 : 0)
            : 0;
        const sprint = !!pad?.buttons[6]?.pressed;
        const lookX = pad ? deadZone(pad.axes[2]) : 0;
        const lookY = pad ? deadZone(pad.axes[3]) : 0;
        const last = this.last;

        if (forward !== last.forward || strafe !== last.strafe) {
            this.emit({ type: 'move', source: 'gamepad', forward, strafe });
        }

        if (lift !== last.lift) {
            this.emit({ type: 'lift', source: 'gamepad', value: lift });
        }

        if (sprint !== last.sprint) {
            this.emit({ type: 'sprint', source: 'gamepad', on: sprint });
        }

        if (lookX || lookY || last.lookX || last.lookY) {
            this.emit({ type: 'lookRate', x: lookX, y: lookY });
        }

        this.last = { forward, strafe, lift, sprint, lookX, lookY };
    }

    private emit(intent: Intent): void {
        this.emitter.emit(intent);
    }
}
