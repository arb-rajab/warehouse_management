import type { Locale, Slot } from '../domain/types';
import { palletName } from '../i18n/format';
import { el, ltr } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';
import { slotCode } from './slotInfo';

export interface SelectionState {
    slot: Slot | null;
    locale: Locale;
}

/** After a far-away click flies in: the picked slot with "Rack details" and "Walk here". */
export class SelectionPill extends SliceView<SelectionState> {
    readonly element = el('div', { class: 'wms-pill wms-selection' });

    constructor(
        private readonly slotEl: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<SelectionState>>[0],
        i18n: ConstructorParameters<typeof SliceView<SelectionState>>[1],
        private readonly intents: {
            openRack(slot: Slot): void;
            walkHere(slot: Slot): void;
            show(visible: boolean): void;
        },
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.slotEl.append(this.element);
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        const { slot, locale } = this.state;

        this.intents.show(slot !== null);

        if (!slot) {
            this.element.replaceChildren();

            return;
        }

        const rack = el('button', {
            class: 'wms-btn wms-btn--ghost',
            attrs: { type: 'button' },
            text: this.t('rackDetails'),
        });
        const walk = el(
            'button',
            { class: 'wms-btn wms-btn--primary', attrs: { type: 'button' } },
            icon('walk'),
            this.t('walkHere'),
        );

        this.on(rack, 'click', () => this.intents.openRack(slot));
        this.on(walk, 'click', () => this.intents.walkHere(slot));
        this.element.replaceChildren(
            slotCode(slot.code),
            el(
                'div',
                { class: 'wms-selection__who' },
                el('b', {
                    class: 'wms-bidi',
                    text:
                        slot.state === 'empty'
                            ? this.t('empty')
                            : palletName(slot.pallet, locale),
                }),
                el(
                    'small',
                    {},
                    `${this.t('aisle')} `,
                    ltr(slot.row),
                    ` · ${this.t('column')} ${slot.col}`,
                ),
            ),
            rack,
            walk,
        );
    }
}
