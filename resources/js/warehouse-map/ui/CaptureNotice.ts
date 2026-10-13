import { el } from '../utils/dom';
import { SliceView } from './SliceView';

export interface CaptureState {
    /** Free walk with a mouse, the mouse not captured, nothing else open on top. */
    visible: boolean;
}

/** "Click to look around": shown when Esc (or a dialog) gave the mouse back; the next click takes it again. */
export class CaptureNotice extends SliceView<CaptureState> {
    readonly element = el('div', { class: 'wms-pill wms-notice' });

    constructor(
        private readonly slotEl: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<CaptureState>>[0],
        i18n: ConstructorParameters<typeof SliceView<CaptureState>>[1],
        private readonly show: (visible: boolean) => void,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.slotEl.append(this.element);
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        this.show(this.state.visible);
        this.element.replaceChildren(
            el('b', { text: this.t('captureTitle') }),
            el('small', { text: this.t('captureSub') }),
        );
    }
}
