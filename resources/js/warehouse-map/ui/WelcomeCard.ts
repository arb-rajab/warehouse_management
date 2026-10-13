import { THRESHOLDS } from '../config';
import { el, kbd } from '../utils/dom';
import { SliceView } from './SliceView';

export interface WelcomeState {
    /** Free walk with a mouse: the only place the card makes sense. */
    eligible: boolean;
    walkedEnough: boolean;
}

export interface WelcomeIntents {
    visits(): number;
    countVisit(): void;
    setVisible(visible: boolean): void;
}

/**
 * The first two or three visits to the walk mode: a card with the keys and
 * the mouse. It goes away by itself after a few seconds of walking, or when
 * you leave the free walk.
 */
export class WelcomeCard extends SliceView<WelcomeState> {
    readonly element = el('div', { class: 'wms-pill wms-welcome' });
    private showing = false;
    private wasEligible = false;
    private timer: ReturnType<typeof setTimeout> | undefined;

    constructor(
        private readonly slotEl: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<WelcomeState>>[0],
        i18n: ConstructorParameters<typeof SliceView<WelcomeState>>[1],
        private readonly intents: WelcomeIntents,
        private readonly show: (visible: boolean) => void,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.slotEl.append(this.element);
        this.own(() => clearTimeout(this.timer));
    }

    protected onDispose(): void {
        this.element.remove();
    }

    private setShowing(showing: boolean): void {
        if (this.showing === showing) {
            return;
        }

        this.showing = showing;
        this.show(showing);
        this.intents.setVisible(showing);
        clearTimeout(this.timer);

        if (showing) {
            this.timer = setTimeout(
                () => this.setShowing(false),
                THRESHOLDS.welcomeMs,
            );
        }
    }

    protected render(): void {
        const { eligible, walkedEnough } = this.state;

        if (
            eligible &&
            !this.wasEligible &&
            this.intents.visits() < THRESHOLDS.welcomeVisits
        ) {
            this.intents.countVisit();
            this.setShowing(true);
        }

        if (this.showing && (!eligible || walkedEnough)) {
            this.setShowing(false);
        }

        this.wasEligible = eligible;

        if (this.showing) {
            this.paint();
        }
    }

    private paint(): void {
        const t = (key: string) => this.t(key);

        this.element.replaceChildren(
            el(
                'div',
                { class: 'wms-welcome__title' },
                t('welcomeTitle'),
                el('small', { text: t('welcomeSub') }),
            ),
            el(
                'div',
                { class: 'wms-welcome__grid' },
                el(
                    'div',
                    {},
                    el(
                        'div',
                        { class: 'wms-wasd' },
                        ...kbd('W', 'A', 'S', 'D').map(
                            (k, i) => (
                                k.classList.add(`wms-wasd__${'wasd'[i]}`),
                                k
                            ),
                        ),
                    ),
                    el('div', {
                        class: 'wms-welcome__caption',
                        text: t('wWalk'),
                    }),
                ),
                el(
                    'div',
                    { class: 'wms-welcome__col wms-welcome__col--mid' },
                    el('div', { class: 'wms-mouse-icon' }),
                    el('b', { text: t('wLook') }),
                    el('span', { text: t('wLookSub') }),
                ),
                el(
                    'div',
                    { class: 'wms-welcome__col' },
                    el(
                        'div',
                        { class: 'wms-welcome__row' },
                        ...kbd('Shift'),
                        el('span', { text: t('wSprint') }),
                    ),
                    el(
                        'div',
                        { class: 'wms-welcome__row' },
                        ...kbd('Space'),
                        el('span', { text: t('wUp') }),
                        ...kbd('Z'),
                        el('span', { text: t('wDown') }),
                    ),
                    el(
                        'div',
                        { class: 'wms-welcome__row' },
                        ...kbd(t('kClick')),
                        el('span', { text: t('wInspect') }),
                    ),
                ),
            ),
            el(
                'div',
                { class: 'wms-welcome__foot' },
                el('span', {}, ...kbd('Esc'), ` ${t('wEsc')}`),
                el('span', {}, ...kbd('G'), ` ${t('wGuided')}`),
                el('span', {}, ...kbd('O'), ` ${t('wOverview')}`),
                el('span', {}, ...kbd('?'), ` ${t('wHelp')}`),
            ),
        );
    }
}
