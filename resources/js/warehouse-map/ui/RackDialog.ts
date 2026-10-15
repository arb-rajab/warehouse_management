import { isMatch } from '../domain/MatchIndex';
import { columnSlots } from '../domain/normalizeRows';
import type { Locale, MatchIndex, Slot, Warehouse } from '../domain/types';
import { palletName } from '../i18n/format';
import type { RackRef, ViewKind } from '../state/state';
import { el, ltr } from '../utils/dom';
import { Dialog, dialogHead } from './Dialog';
import { icon } from './icons';
import { SliceView } from './SliceView';
import { boxesText, expiryText, meter, stateLabel, tag } from './slotInfo';

export interface RackDialogState {
    rack: RackRef | null;
    warehouse: Warehouse | null;
    match: MatchIndex;
    view: ViewKind;
    locale: Locale;
}

export interface RackDialogIntents {
    close(): void;
    copyLink(slot: Slot): void;
    showIn3d(slot: Slot): void;
    showInPlan(slot: Slot): void;
}

/** One rack column: its shelves stacked, top level first, never rearranged. */
export class RackDialog extends SliceView<RackDialogState> {
    private readonly dialog: Dialog;

    constructor(
        host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<RackDialogState>>[0],
        i18n: ConstructorParameters<typeof SliceView<RackDialogState>>[1],
        private readonly intents: RackDialogIntents,
    ) {
        super(slice, i18n);
        this.dialog = new Dialog(host, () => intents.close());
    }

    protected build(): void {
        this.dialog.mount();
        this.own(() => this.dialog.dispose());
    }

    protected render(): void {
        const { rack, warehouse } = this.state;

        if (!rack || !warehouse) {
            this.dialog.hide();

            return;
        }

        const slots = columnSlots(warehouse, rack.row, rack.col)
            .slice()
            .sort((a, b) => b.level - a.level);
        const content = [
            this.header(rack),
            this.body(slots, rack),
            this.footer(slots, rack),
        ];

        if (this.dialog.isOpen) {
            this.dialog.refresh(...content);
        } else {
            this.dialog.show(...content);
            this.dialog.panel
                .querySelector('.wms-shelf.is-target')
                ?.scrollIntoView?.({ block: 'center' });
        }
    }

    private header(rack: RackRef): HTMLElement {
        return dialogHead(
            [
                `${this.t('aisle')} `,
                ltr(rack.row),
                el('small', { text: ' | ' }),
                ` ${this.t('column')} ${rack.col}`,
            ],
            this.t('close'),
            () => this.intents.close(),
            icon('x'),
        );
    }

    private body(slots: Slot[], rack: RackRef): HTMLElement {
        const shelves = slots.map((slot) =>
            this.shelf(slot, rack.level === slot.level),
        );

        return el(
            'div',
            { class: 'wms-dialog__body' },
            el('div', { class: 'wms-rack' }, ...shelves),
        );
    }

    private shelf(slot: Slot, isTarget: boolean): HTMLElement {
        const { match, locale } = this.state;
        const t = (key: string, params?: Record<string, string | number>) =>
            this.t(key, params);
        const dimmed = match.filtering && !isMatch(match, slot);
        const classes = ['wms-shelf'];

        if (isTarget) {
            classes.push('is-target');
        }

        if (dimmed) {
            classes.push('is-dimmed');
        }

        const level = el(
            'div',
            { class: 'wms-shelf__level' },
            t('level'),
            el('b', { text: String(slot.level) }),
        );

        if (slot.state === 'empty') {
            return el(
                'div',
                { class: classes.join(' '), data: { level: slot.level } },
                level,
                el('div', { class: 'wms-box wms-box--empty' }, icon('box')),
                el(
                    'div',
                    { class: 'wms-shelf__info' },
                    el('div', {
                        class: 'wms-shelf__name wms-shelf__name--muted',
                        text: t('empty'),
                    }),
                    el(
                        'div',
                        { class: 'wms-shelf__meta' },
                        el('span', { text: t('emptySub') }),
                        slot.active ? null : tag(t('outOfService'), 'off'),
                    ),
                ),
            );
        }

        const expiry = expiryText(slot, locale, t);
        const boxes = boxesText(slot, t);
        const bar = meter(slot, 'wms-meter');

        return el(
            'div',
            { class: classes.join(' '), data: { level: slot.level } },
            level,
            el('div', { class: `wms-box wms-box--${slot.state}` }, icon('box')),
            el(
                'div',
                { class: 'wms-shelf__info' },
                el('div', {
                    class: 'wms-shelf__name wms-bidi',
                    text: palletName(slot.pallet, locale),
                }),
                el(
                    'div',
                    { class: 'wms-shelf__meta' },
                    tag(stateLabel(slot.state, t), slot.state),
                    expiry
                        ? el('span', {
                              class: `wms-exp wms-exp--${expiry.tone}`,
                              text: expiry.text,
                          })
                        : null,
                    slot.active ? null : tag(t('outOfService'), 'off'),
                ),
                bar,
                boxes
                    ? el(
                          'div',
                          { class: 'wms-shelf__meta wms-shelf__meta--boxes' },
                          el('span', { text: boxes }),
                      )
                    : null,
            ),
        );
    }

    private footer(slots: Slot[], rack: RackRef): HTMLElement {
        const view = this.state.view;
        const target =
            (rack.level === null
                ? null
                : slots.find((s) => s.level === rack.level)) ?? null;
        const pick = (): Slot =>
            target ?? (view === '3d' ? slots[slots.length - 1] : slots[0]);
        const copy = el(
            'button',
            { class: 'wms-btn wms-btn--soft', attrs: { type: 'button' } },
            icon('pin'),
            this.t('copyLink'),
        );
        const other =
            view === '2d'
                ? el(
                      'button',
                      {
                          class: 'wms-btn wms-btn--primary wms-btn--grow',
                          attrs: { type: 'button' },
                      },
                      icon('walk'),
                      this.t('showIn3d'),
                  )
                : el(
                      'button',
                      {
                          class: 'wms-btn wms-btn--primary wms-btn--grow',
                          attrs: { type: 'button' },
                      },
                      icon('map'),
                      this.t('showIn2d'),
                  );

        this.on(copy, 'click', () => this.intents.copyLink(pick()));
        this.on(other, 'click', () =>
            view === '2d'
                ? this.intents.showIn3d(pick())
                : this.intents.showInPlan(pick()),
        );

        return el('div', { class: 'wms-dialog__foot' }, copy, other);
    }
}
