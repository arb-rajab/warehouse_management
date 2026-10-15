import type { Locale, Slot } from '../domain/types';
import { palletName } from '../i18n/format';
import { el, kbd, replaceChildrenOf } from '../utils/dom';
import { SliceView } from './SliceView';
import {
    boxesText,
    expiryText,
    meter,
    slotCode,
    stateDot,
    stateLabel,
    tag,
} from './slotInfo';

export interface NameCardState {
    slot: Slot | null;
    visible: boolean;
    inputKind: 'mouse' | 'touch';
    locale: Locale;
}

/** Describes the pallet under the crosshair (or cursor): code, state, name, expiry, boxes left. Information only, it takes no clicks (a tap opens the rack on touch). */
export class NameCard extends SliceView<NameCardState> {
    readonly element = el('div', { class: 'wms-pill wms-card' });

    constructor(
        private readonly slotEl: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<NameCardState>>[0],
        i18n: ConstructorParameters<typeof SliceView<NameCardState>>[1],
        private readonly intents: {
            openRack(slot: Slot): void;
            show(visible: boolean): void;
        },
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.slotEl.append(this.element);
        this.on(this.element, 'click', () => {
            const { slot, inputKind } = this.state;

            if (inputKind === 'touch' && slot) {
                this.intents.openRack(slot);
            }
        });
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        const { slot, visible, inputKind, locale } = this.state;
        const shown = visible && slot !== null;

        this.intents.show(shown);

        if (!shown || !slot) {
            return;
        }

        const t = (key: string, params?: Record<string, string | number>) =>
            this.t(key, params);
        const empty = slot.state === 'empty';
        const expiry = expiryText(slot, locale, t);
        const boxes = boxesText(slot, t);
        const bar = empty ? null : meter(slot, 'wms-card__meter');

        replaceChildrenOf(
            this.element,
            el(
                'div',
                { class: 'wms-card__top' },
                slotCode(slot.code),
                el(
                    'span',
                    { class: 'wms-card__state' },
                    stateDot(slot.state),
                    stateLabel(slot.state, t),
                ),
                slot.active ? null : tag(t('outOfService'), 'off'),
            ),
            el('div', {
                class: 'wms-card__name wms-bidi',
                text: empty ? t('empty') : palletName(slot.pallet, locale),
            }),
            empty
                ? null
                : el(
                      'div',
                      { class: 'wms-card__sub' },
                      expiry
                          ? el('span', {
                                class: `wms-exp wms-exp--${expiry.tone}`,
                                text: expiry.text,
                            })
                          : null,
                      boxes ? el('span', { text: boxes }) : null,
                  ),
            bar,
            el(
                'div',
                { class: 'wms-card__hint' },
                ...(inputKind === 'touch'
                    ? [t('tapDetails')]
                    : [
                          ...kbd(t('kLClick')),
                          el('span', { text: t('orWord') }),
                          ...kbd('Enter'),
                          el('span', { text: t('opensDetails') }),
                      ]),
            ),
        );
    }
}
