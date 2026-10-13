import type { NavMode } from '../state/state';
import { el } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface WalkBarState {
    nav: NavMode;
    magnet: boolean;
    inputKind: 'mouse' | 'touch';
}

export interface WalkBarIntents {
    goOverview(): void;
    setNav(nav: NavMode): void;
    toggleMagnet(): void;
    openHelp(): void;
    toggleFullscreen(): void;
}

/** While walking: back to the overview, free / guided, the auto-align switch, help and full screen. */
export class WalkBar extends SliceView<WalkBarState> {
    readonly element = el('div', {
        class: 'wms-walkbar wms-hud wms-pill',
        attrs: { role: 'toolbar' },
    });
    private readonly backLabel = el('span', {
        class: 'wms-walkbar__back-label',
    });
    private readonly back = el(
        'button',
        { class: 'wms-chip', attrs: { type: 'button' } },
        icon('back'),
        this.backLabel,
        el('kbd', { class: 'wms-mouse-only', text: 'O' }),
    );
    private readonly free = el('button', {
        class: 'wms-chip',
        attrs: { type: 'button' },
    });
    private readonly guided = el('button', {
        class: 'wms-chip',
        attrs: { type: 'button' },
    });
    private readonly freeLabel = el('span');
    private readonly guidedLabel = el('span');
    private readonly magnet = el(
        'button',
        { class: 'wms-chip wms-walkbar__magnet', attrs: { type: 'button' } },
        icon('magnet'),
    );
    private readonly help = el(
        'button',
        { class: 'wms-icon-btn', attrs: { type: 'button' } },
        icon('key'),
    );
    private readonly fullscreen = el(
        'button',
        { class: 'wms-icon-btn wms-mouse-only', attrs: { type: 'button' } },
        icon('full'),
    );

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<WalkBarState>>[0],
        i18n: ConstructorParameters<typeof SliceView<WalkBarState>>[1],
        private readonly intents: WalkBarIntents,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.free.append(
            this.freeLabel,
            el('kbd', { class: 'wms-mouse-only', text: 'G' }),
        );
        this.guided.append(this.guidedLabel);
        this.element.append(
            this.back,
            el('span', { class: 'wms-sep' }),
            this.free,
            this.guided,
            this.magnet,
            el('span', { class: 'wms-sep' }),
            this.help,
            this.fullscreen,
        );
        this.host.append(this.element);

        this.on(this.back, 'click', () => this.intents.goOverview());
        this.on(this.free, 'click', () => this.intents.setNav('free'));
        this.on(this.guided, 'click', () => this.intents.setNav('guided'));
        this.on(this.magnet, 'click', () => this.intents.toggleMagnet());
        this.on(this.help, 'click', () => this.intents.openHelp());
        this.on(this.fullscreen, 'click', () =>
            this.intents.toggleFullscreen(),
        );
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        const { nav, magnet } = this.state;

        this.backLabel.textContent = this.t('back');
        this.freeLabel.textContent = this.t('navFree');
        this.guidedLabel.textContent = this.t('navGuided');
        this.free.classList.toggle('is-on', nav === 'free');
        this.guided.classList.toggle('is-on', nav === 'guided');
        this.free.setAttribute('aria-pressed', String(nav === 'free'));
        this.guided.setAttribute('aria-pressed', String(nav === 'guided'));
        this.magnet.title = this.t('magnet');
        this.magnet.setAttribute('aria-label', this.t('magnet'));
        this.magnet.setAttribute('aria-pressed', String(magnet));
        this.magnet.classList.toggle('is-set', magnet);
        this.help.title = this.t('help');
        this.help.setAttribute('aria-label', this.t('help'));
        this.fullscreen.title = this.t('fullscreen');
        this.fullscreen.setAttribute('aria-label', this.t('fullscreen'));
    }
}
