export type InputSource = 'keyboard' | 'mouse' | 'touch' | 'gamepad';
export type PointerKind = 'mouse' | 'touch' | 'pen';

/**
 * What the user means, whichever device said it. The camera modes consume
 * only these; adding a device means adding an adapter, never editing a mode.
 *
 * Continuous intents (`move`, `lift`, `sprint`) carry their `source` so two
 * devices held at once add up instead of overwriting each other.
 */
export type Intent =
    /** Held walking axes, -1..1: forward/back and strafe right/left. Pans the overview. */
    | { type: 'move'; source: InputSource; forward: number; strafe: number }
    /** Held up/down in free walk, -1..1. */
    | { type: 'lift'; source: InputSource; value: number }
    | { type: 'sprint'; source: InputSource; on: boolean }
    /** A look delta: dx > 0 looks right, dy > 0 looks down; `rate` is radians per unit. */
    | { type: 'look'; dx: number; dy: number; rate: number }
    /** A held stick that turns the view continuously, -1..1 each way. */
    | { type: 'lookRate'; x: number; y: number }
    /** A grabbing drag on the scene: orbit/look with the primary button, pan with the secondary. */
    | {
          type: 'drag';
          dx: number;
          dy: number;
          secondary: boolean;
          pointer: PointerKind;
      }
    | { type: 'dragEnd'; moved: boolean }
    | { type: 'pinch'; ratio: number; dx: number; dy: number }
    | { type: 'wheel'; delta: number }
    | { type: 'zoomLens'; on: boolean }
    | { type: 'zoomStep'; factor: number }
    /** Guided walking: whole bays forward / to the right. */
    | { type: 'step'; forward: number; strafe: number }
    | { type: 'turn'; quarters: number }
    | { type: 'changeLevel'; delta: number }
    | { type: 'tap'; x: number; y: number; pointer: PointerKind }
    | { type: 'hover'; x: number; y: number }
    /** Open whatever is under the crosshair. */
    | { type: 'inspect' }
    | { type: 'toggleNav' }
    | { type: 'goOverview' }
    | { type: 'toggleMinimap' }
    | { type: 'toggleKeys' }
    | { type: 'aisle'; index: number }
    /** The browser took or gave back the mouse (pointer lock). */
    | { type: 'captureChange'; captured: boolean };

export type IntentHandler = (intent: Intent) => void;

/** All an adapter exposes: a stream of intents. */
export interface IntentSource {
    onIntent(handler: IntentHandler): () => void;
}

/** Lets the scene take and give back the mouse, without knowing the adapter. */
export interface PointerCapture {
    readonly captured: boolean;
    request(): void;
    release(): void;
}

/** Shared plumbing for adapters: a handler list and an `emit`. */
export class IntentEmitter implements IntentSource {
    private readonly handlers = new Set<IntentHandler>();

    onIntent(handler: IntentHandler): () => void {
        this.handlers.add(handler);

        return () => this.handlers.delete(handler);
    }

    emit(intent: Intent): void {
        for (const handler of [...this.handlers]) {
            handler(intent);
        }
    }
}

/** What an adapter must know about the page to translate keys and gestures. */
export interface InputContext {
    /** The 3D view is showing and no dialog is open. */
    isSceneInteractive(): boolean;
    mode(): 'overview' | 'walk' | 'fly';
    nav(): 'free' | 'guided';
    aisleCount(): number;
}
