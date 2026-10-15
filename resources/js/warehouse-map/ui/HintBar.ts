import { THRESHOLDS } from '../config';
import type { Hint } from '../state/state';
import { el } from '../utils/dom';
import { SliceView } from './SliceView';

export interface HintState {
    hint: Hint | null;
    view: '2d' | '3d';
    mode: 'overview' | 'walk' | 'fly';
    inputKind: 'mouse' | 'touch';
    nav: 'free' | 'guided';
}

/** The one-line hint under the bars: how to move the map you are looking at. It appears when the view changes, then goes away by itself. */
export class HintBar extends SliceView<HintState> {
    readonly element = el('div', { class: 'wms-pill wms-hint' });
    readonly touchElement = el('div', {
        class: 'wms-pill wms-hint wms-hint--touch',
    });
    private timer: ReturnType<typeof setTimeout> | undefined;
    private lastSeq = 0;
    private lastKey = '';

    constructor(
        private readonly hintSlot: HTMLElement,
        private readonly touchSlot: HTMLElement,
        private readonly show: (
            slot: 'hint' | 'touchhint',
            visible: boolean,
        ) => void,
        slice: ConstructorParameters<typeof SliceView<HintState>>[0],
        i18n: ConstructorParameters<typeof SliceView<HintState>>[1],
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.hintSlot.append(this.element);
        this.touchSlot.append(this.touchElement);
        this.own(() => clearTimeout(this.timer));
    }

    protected onDispose(): void {
        this.element.remove();
        this.touchElement.remove();
    }

    private flash(text: string | null, ms: number): void {
        clearTimeout(this.timer);

        if (!text) {
            this.show('hint', false);

            return;
        }

        this.element.textContent = text;
        this.show('hint', true);
        this.timer = setTimeout(() => this.show('hint', false), ms);
    }

    private defaultKey(): string | null {
        const { view, mode, inputKind } = this.state;

        if (view === '2d') {
            return inputKind === 'touch' ? 'hintPlanTouch' : 'hintPlan';
        }

        if (mode === 'overview') {
            return inputKind === 'touch' ? 'hintOverTouch' : 'hintOver';
        }

        return null;
    }

    protected render(): void {
        const { hint, view, mode, inputKind, nav } = this.state;
        const key = `${view}|${mode}|${inputKind}`;

        this.touchElement.textContent = this.t('touchHint');
        this.show(
            'touchhint',
            inputKind === 'touch' &&
                view === '3d' &&
                mode !== 'overview' &&
                nav === 'free',
        );

        if (hint && hint.seq !== this.lastSeq) {
            this.lastSeq = hint.seq;
            this.flash(hint.text, hint.ms);

            return;
        }

        if (key !== this.lastKey) {
            this.lastKey = key;

            const text = this.defaultKey();

            this.flash(text ? this.t(text) : null, THRESHOLDS.hintMs);
        }
    }
}
