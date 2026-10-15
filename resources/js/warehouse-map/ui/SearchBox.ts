import { THRESHOLDS } from '../config';
import { debounce } from '../utils/debounce';
import type { Debounced } from '../utils/debounce';
import { el } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface SearchState {
    query: string;
}

export interface SearchIntents {
    setText(query: string): void;
    /** The typed words become the filter (after the debounce). */
    commit(): void;
    clear(): void;
}

/** Product search: every word must match, in English or Arabic. */
export class SearchBox extends SliceView<SearchState> {
    readonly element = el('label', { class: 'wms-search' });
    readonly input = el('input', {
        class: 'wms-search__input',
        attrs: {
            type: 'text',
            inputmode: 'search',
            enterkeyhint: 'search',
            autocomplete: 'off',
            autocapitalize: 'off',
            spellcheck: 'false',
            dir: 'auto',
        },
    });
    private readonly clearButton = el(
        'button',
        { class: 'wms-search__clear', attrs: { type: 'button' } },
        icon('x'),
    );
    private commitLater!: Debounced<[]>;

    constructor(
        slice: ConstructorParameters<typeof SliceView<SearchState>>[0],
        i18n: ConstructorParameters<typeof SliceView<SearchState>>[1],
        private readonly intents: SearchIntents,
    ) {
        super(slice, i18n);
    }

    focus(): void {
        this.input.focus();
        this.input.select();
    }

    protected build(): void {
        this.element.append(icon('search'), this.input, this.clearButton);
        this.commitLater = debounce(
            () => this.intents.commit(),
            THRESHOLDS.searchDebounceMs,
        );
        this.own(() => this.commitLater.cancel());

        this.on(this.input, 'input', () => {
            this.intents.setText(this.input.value);
            this.commitLater();
        });
        this.on(this.clearButton, 'click', () => {
            this.commitLater.cancel();
            this.intents.clear();
            this.input.focus();
        });
    }

    protected render(): void {
        const { query } = this.state;

        if (this.input.value !== query) {
            this.input.value = query;
        }

        this.input.placeholder = this.t('searchPh');
        this.input.setAttribute('aria-label', this.t('searchPh'));
        this.clearButton.setAttribute('aria-label', this.t('clearSearch'));
        this.element.classList.toggle('has-text', query.length > 0);
    }
}
