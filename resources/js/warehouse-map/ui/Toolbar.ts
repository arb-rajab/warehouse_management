import { View } from '../core/View';
import type { Locale } from '../domain/types';
import type { Slice } from '../state/slices';
import type { ViewKind } from '../state/state';
import { el } from '../utils/dom';
import { icon } from './icons';
import type { SearchBox } from './SearchBox';
import type { Translator } from './SliceView';
import type { StateChips } from './StateChips';

export interface ToolbarState {
    view: ViewKind;
    theme: 'light' | 'dark';
    searchOpen: boolean;
    locale: Locale;
}

export interface ToolbarIntents {
    setView(view: ViewKind): void;
    openLegend(): void;
    openHelp(): void;
    toggleTheme(): void;
    switchLocale(locale: Locale): void;
    toggleSearchOpen(): void;
}

/**
 * The top bar: brand, the product search with its chips, the 2D | 3D toggle
 * and the legend / help / theme / language buttons. On a phone the search
 * folds behind a button so the bar never wraps over the content.
 */
export class Toolbar extends View {
    readonly element = el('div', {
        class: 'wms-topbar wms-hud',
        attrs: { role: 'toolbar' },
    });
    private readonly title = el('b', { class: 'wms-brand__title' });
    private readonly subtitle = el('small', { class: 'wms-brand__sub' });
    private readonly toggleButtons = new Map<ViewKind, HTMLButtonElement>();
    private readonly searchToggle = el(
        'button',
        { class: 'wms-icon-btn wms-search-toggle', attrs: { type: 'button' } },
        icon('search'),
    );
    private readonly legendButton = el(
        'button',
        { class: 'wms-icon-btn', attrs: { type: 'button' } },
        icon('info'),
    );
    private readonly helpButton = el(
        'button',
        { class: 'wms-icon-btn wms-icon-btn--keys', attrs: { type: 'button' } },
        icon('key'),
    );
    private readonly guideLink = el(
        'a',
        { class: 'wms-icon-btn wms-icon-btn--optional wms-guide' },
        icon('help'),
    );
    private readonly themeButton = el(
        'button',
        {
            class: 'wms-icon-btn wms-icon-btn--optional',
            attrs: { type: 'button' },
        },
        icon('sun'),
    );
    private readonly langButton = el('button', {
        class: 'wms-icon-btn wms-icon-btn--lang',
        attrs: { type: 'button' },
    });
    private readonly toggle = el('div', {
        class: 'wms-seg',
        attrs: { role: 'group' },
    });
    private state: ToolbarState;

    constructor(
        private readonly host: HTMLElement,
        private readonly slice: Slice<ToolbarState>,
        private readonly i18n: Translator,
        private readonly intents: ToolbarIntents,
        private readonly search: SearchBox,
        private readonly chips: StateChips,
        private readonly helpUrl?: string,
    ) {
        super();
        this.state = slice.get();
    }

    mount(): void {
        const brand = el(
            'div',
            { class: 'wms-pill wms-bar wms-brand' },
            icon('warehouse', 'wms-brand__logo'),
            el('div', {}, this.title, this.subtitle),
        );
        const filters = el(
            'div',
            { class: 'wms-pill wms-bar wms-filters' },
            this.searchToggle,
            this.search.element,
            this.chips.element,
        );
        const actions = el(
            'div',
            { class: 'wms-pill wms-bar wms-actions' },
            this.toggle,
            this.legendButton,
            this.helpButton,
            ...(this.helpUrl ? [this.guideLink] : []),
            this.themeButton,
            this.langButton,
        );

        for (const view of ['2d', '3d'] as const) {
            const button = el('button', {
                attrs: { type: 'button' },
                data: { view },
                text: view.toUpperCase(),
            });

            this.on(button, 'click', () => this.intents.setView(view));
            this.toggleButtons.set(view, button);
            this.toggle.append(button);
        }

        this.element.append(
            brand,
            filters,
            el('div', { class: 'wms-spacer' }),
            actions,
        );
        this.host.append(this.element);

        this.on(this.searchToggle, 'click', () =>
            this.intents.toggleSearchOpen(),
        );
        this.on(this.legendButton, 'click', () => this.intents.openLegend());
        this.on(this.helpButton, 'click', () => this.intents.openHelp());
        this.on(this.themeButton, 'click', () => this.intents.toggleTheme());
        this.on(this.langButton, 'click', () =>
            this.intents.switchLocale(this.state.locale === 'ar' ? 'en' : 'ar'),
        );

        this.search.mount();
        this.chips.mount();
        this.own(() => this.search.dispose());
        this.own(() => this.chips.dispose());

        if (this.helpUrl) {
            this.guideLink.setAttribute('href', this.helpUrl);
        }

        this.render();
        this.own(this.slice.subscribe((next) => this.update(next)));
        this.own(this.i18n.onChange(() => this.update(this.slice.get())));
    }

    protected onDispose(): void {
        this.element.remove();
    }

    private update(next: ToolbarState): void {
        this.state = next;
        this.render();
    }

    private render(): void {
        const t = (key: string) => this.i18n.t(key);
        const { view, searchOpen, locale } = this.state;

        this.title.textContent = t('appTitle');
        this.subtitle.textContent = t('appSub');
        this.element.setAttribute('aria-label', t('toolbar'));
        this.toggle.setAttribute('aria-label', t('viewToggle'));

        for (const [kind, button] of this.toggleButtons) {
            button.classList.toggle('is-on', kind === view);
            button.setAttribute('aria-pressed', String(kind === view));
        }

        this.element.classList.toggle('is-searching', searchOpen);
        this.searchToggle.setAttribute('aria-label', t('searchPh'));
        this.searchToggle.setAttribute('aria-expanded', String(searchOpen));
        this.legendButton.title = t('legendTitle');
        this.legendButton.setAttribute('aria-label', t('legendTitle'));
        this.helpButton.title = t('help');
        this.helpButton.setAttribute('aria-label', t('help'));
        this.guideLink.title = t('guide');
        this.guideLink.setAttribute('aria-label', t('guide'));
        this.themeButton.title = t('theme');
        this.themeButton.setAttribute('aria-label', t('theme'));
        this.langButton.textContent = locale === 'ar' ? 'EN' : 'ع';
        this.langButton.title = t('language');
        this.langButton.setAttribute('aria-label', t('language'));
    }
}
