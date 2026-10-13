import { el } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface NotchState {
    folded: boolean;
}

/** A small tab at the top edge that folds the toolbar and the camera bar away (the T key does the same). */
export class Notch extends SliceView<NotchState> {
    readonly element = el(
        'button',
        { class: 'wms-notch', attrs: { type: 'button' } },
        icon('up'),
    );

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<NotchState>>[0],
        i18n: ConstructorParameters<typeof SliceView<NotchState>>[1],
        private readonly toggle: () => void,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.host.append(this.element);
        this.on(this.element, 'click', () => this.toggle());
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        this.element.title = this.t('foldBars');
        this.element.setAttribute('aria-label', this.t('foldBars'));
        this.element.setAttribute('aria-expanded', String(!this.state.folded));
    }
}
