import { el } from '../utils/dom';
import { SliceView } from './SliceView';

export interface CrosshairState {
    hot: boolean;
}

/** The crosshair of walk mode; it turns yellow while a pallet is under it. */
export class Crosshair extends SliceView<CrosshairState> {
    readonly element = el('div', {
        class: 'wms-crosshair',
        attrs: { 'aria-hidden': 'true' },
    });

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<CrosshairState>>[0],
        i18n: ConstructorParameters<typeof SliceView<CrosshairState>>[1],
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.host.append(this.element);
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        this.element.classList.toggle('is-hot', this.state.hot);
    }
}
