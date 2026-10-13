import { View } from '../core/View';
import type { Locale } from '../domain/types';
import type { I18n } from '../i18n/I18n';
import type { Slice } from '../state/slices';
import type { InputKindName, NavMode, ViewKind } from '../state/state';

export interface RootState {
    view: ViewKind;
    mode: 'overview' | 'walk';
    nav: NavMode;
    inputKind: InputKindName;
    folded: boolean;
    theme: 'light' | 'dark';
    captured: boolean;
    modal: boolean;
    welcome: boolean;
    magnet: boolean;
    away: boolean;
    locale: Locale;
}

/**
 * Page state lives on the root as `data-*` attributes (never on a class that is
 * also a component), and CSS reads it from there: which view, whether you are
 * walking, which device, whether the toolbar is folded, ...
 */
export class RootAttributes extends View {
    constructor(
        private readonly root: HTMLElement,
        private readonly slice: Slice<RootState>,
        private readonly i18n: Pick<I18n, 'dir' | 'locale' | 'onChange'>,
    ) {
        super();
    }

    mount(): void {
        this.apply(this.slice.get());
        this.own(this.slice.subscribe((state) => this.apply(state)));
        this.own(this.i18n.onChange(() => this.apply(this.slice.get())));
    }

    private apply(s: RootState): void {
        const root = this.root;
        const set = (name: string, value: string | boolean) =>
            root.setAttribute(`data-${name}`, String(value));

        set('view', s.view);
        set('mode', s.mode);
        set('nav', s.nav);
        set('input', s.inputKind);
        set('folded', s.folded);
        set('theme', s.theme);
        set('captured', s.captured);
        set('modal', s.modal);
        set('welcome', s.welcome);
        set('magnet', s.magnet);
        set('away', s.away);
        root.setAttribute('dir', this.i18n.dir);
        root.setAttribute('lang', this.i18n.locale);
    }
}
