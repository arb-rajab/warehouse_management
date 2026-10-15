import { View } from '../core/View';
import type { I18n } from '../i18n/I18n';
import type { Slice } from '../state/slices';

export type Translator = Pick<I18n, 't' | 'onChange' | 'locale'>;

/**
 * A view that renders from one slice of the state and from the language:
 * `build()` creates its static DOM once, `render()` brings it up to date
 * whenever the slice or the language changes. Views never write to the store;
 * they call the intents they were given.
 */
export abstract class SliceView<T extends object> extends View {
    protected state!: T;

    constructor(
        protected readonly slice: Slice<T>,
        protected readonly i18n: Translator,
    ) {
        super();
    }

    mount(): void {
        this.build();
        this.state = this.slice.get();
        this.render();
        this.own(
            this.slice.subscribe((next) => {
                this.state = next;
                this.render();
            }),
        );
        this.own(
            this.i18n.onChange(() => {
                this.state = this.slice.get();
                this.render();
            }),
        );
    }

    protected t(key: string, params?: Record<string, string | number>): string {
        return this.i18n.t(key, params);
    }

    protected abstract build(): void;
    protected abstract render(): void;
}
