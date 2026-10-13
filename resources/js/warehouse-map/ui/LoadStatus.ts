import { el } from '../utils/dom';
import { SliceView } from './SliceView';

export interface LoadState {
    loaded: boolean;
    failed: boolean;
}

/** Shown over the map until the warehouse has arrived (or failed to). */
export class LoadStatus extends SliceView<LoadState> {
    readonly element = el('div', {
        class: 'wms-load',
        attrs: { role: 'status' },
    });
    private readonly message = el('p', { class: 'wms-load__text' });
    private readonly retryButton = el('button', {
        class: 'wms-btn wms-btn--primary',
        attrs: { type: 'button' },
    });

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<LoadState>>[0],
        i18n: ConstructorParameters<typeof SliceView<LoadState>>[1],
        private readonly retry: () => void,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.element.append(this.message, this.retryButton);
        this.host.append(this.element);
        this.on(this.retryButton, 'click', () => this.retry());
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        const { loaded, failed } = this.state;

        this.element.classList.toggle('is-hidden', loaded);
        this.element.classList.toggle('is-failed', failed);
        this.message.textContent = this.t(failed ? 'loadFailed' : 'loading');
        this.retryButton.textContent = this.t('retry');
        this.retryButton.hidden = !failed;
    }
}
