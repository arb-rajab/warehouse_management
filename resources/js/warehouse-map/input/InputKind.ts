import { View } from '../core/View';
import type { InputKindName } from '../state/state';

/**
 * Decides between desktop (keyboard + mouse) and touch controls, and follows
 * whichever device was used last, so a hybrid laptop switches as you change hands.
 */
export class InputKind extends View {
    private kind: InputKindName;
    private touched = false;

    constructor(
        private readonly target: Window,
        private readonly onChange: (kind: InputKindName) => void,
        initialCoarse: boolean,
    ) {
        super();
        this.kind = initialCoarse ? 'touch' : 'mouse';
    }

    get current(): InputKindName {
        return this.kind;
    }

    /** True once a real touch happened: only then may the page vibrate. */
    get hasTouched(): boolean {
        return this.touched;
    }

    mount(): void {
        this.on(
            this.target,
            'pointerdown',
            (event: PointerEvent) => {
                if (event.pointerType === 'touch') {
                    this.touched = true;
                    this.set('touch');
                }
            },
            { capture: true },
        );
        this.on(
            this.target,
            'pointermove',
            (event: PointerEvent) => {
                if (event.pointerType === 'mouse' && this.kind === 'touch') {
                    this.set('mouse');
                }
            },
            { capture: true },
        );
    }

    private set(kind: InputKindName): void {
        if (kind === this.kind) {
            return;
        }

        this.kind = kind;
        this.onChange(kind);
    }
}
