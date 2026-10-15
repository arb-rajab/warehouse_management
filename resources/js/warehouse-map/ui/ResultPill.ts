import { el } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface ResultState {
    filtering: boolean;
    count: number;
}

/** The result pill: how many slots match; it opens the Matched Products drawer. It keeps its place whether shown or not. */
export class ResultPill extends SliceView<ResultState> {
    readonly element = el('button', {
        class: 'wms-pill wms-result-pill',
        attrs: { type: 'button' },
    });
    private readonly label = el('span');

    constructor(
        slice: ConstructorParameters<typeof SliceView<ResultState>>[0],
        i18n: ConstructorParameters<typeof SliceView<ResultState>>[1],
        private readonly open: () => void,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.element.append(icon('box'), this.label);
        this.on(this.element, 'click', () => this.open());
    }

    protected render(): void {
        const { filtering, count } = this.state;

        this.element.classList.toggle('is-shown', filtering);
        this.element.toggleAttribute('hidden', false);
        this.element.tabIndex = filtering ? 0 : -1;
        this.element.setAttribute('aria-hidden', String(!filtering));
        this.label.textContent =
            count > 0 ? this.t('matches', { count }) : this.t('noMatches');
    }
}
