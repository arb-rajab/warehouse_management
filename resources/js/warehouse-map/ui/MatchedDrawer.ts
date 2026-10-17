import { productHue } from '../domain/expiry';
import type { Locale, MatchIndex, Slot, Warehouse } from '../domain/types';
import { formatDate, palletName } from '../i18n/format';
import { el } from '../utils/dom';
import { dialogHead } from './Dialog';
import { icon } from './icons';
import { productStatusBadge } from './ProductPicker';
import { SliceView } from './SliceView';
import { expiryText, slotCode, stateDot, tag } from './slotInfo';

export interface DrawerState {
    open: boolean;
    warehouse: Warehouse | null;
    match: MatchIndex;
    locale: Locale;
}

type StateTab = 'all' | 'full' | 'opened';

/**
 * The Matched Products drawer: matches grouped by product, earliest expiry
 * first. A product that is no longer sold and a slot that is out of service
 * are marked, so an inactive match never reads like an active one.
 */
export class MatchedDrawer extends SliceView<DrawerState> {
    readonly element = el('aside', {
        class: 'wms-drawer',
        attrs: { 'aria-hidden': 'true' },
    });
    private tab: StateTab = 'all';
    private readonly expanded = new Set<number>();

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<DrawerState>>[0],
        i18n: ConstructorParameters<typeof SliceView<DrawerState>>[1],
        private readonly intents: {
            close(): void;
            viewOnMap(slot: Slot): void;
        },
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.host.append(this.element);
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        const { open } = this.state;

        this.element.classList.toggle('is-open', open);
        this.element.setAttribute('aria-hidden', String(!open));
        this.element.toggleAttribute('inert', !open);

        if (!open) {
            return;
        }

        const { warehouse, match, locale } = this.state;
        const hits = (warehouse?.slots ?? []).filter(
            (s) => match.ids.has(s.id) && s.state !== 'empty',
        );
        const sealed = hits.filter((s) => s.state === 'full').length;
        const partial = hits.length - sealed;
        const shown = hits.filter(
            (s) => this.tab === 'all' || s.state === this.tab,
        );
        const groups = new Map<number, Slot[]>();

        for (const slot of shown) {
            const id = slot.pallet!.productId;

            groups.set(id, [...(groups.get(id) ?? []), slot]);
        }

        const list = [...groups.values()].sort((a, b) => b.length - a.length);

        this.element.replaceChildren(
            dialogHead(
                [this.t('seeMatched')],
                this.t('close'),
                () => this.intents.close(),
                icon('x'),
            ),
            el(
                'div',
                { class: 'wms-stats' },
                this.stat('all', hits.length, this.t('total')),
                this.stat('full', sealed, this.t('sealed')),
                this.stat('opened', partial, this.t('partial')),
            ),
            el(
                'div',
                { class: 'wms-dialog__body wms-drawer__body' },
                ...(list.length > 0
                    ? list.map((group) => this.group(group, locale))
                    : [
                          el('p', {
                              class: 'wms-drawer__empty',
                              text: this.t('noMatches'),
                          }),
                      ]),
            ),
        );
    }

    private stat(tab: StateTab, count: number, label: string): HTMLElement {
        const button = el(
            'button',
            {
                class: `wms-stat${this.tab === tab ? ' is-on' : ''}`,
                attrs: { type: 'button' },
            },
            el('b', { text: String(count) }),
            label,
        );

        this.on(button, 'click', () => {
            this.tab = tab;
            this.state = this.slice.get();
            this.render();
        });

        return button;
    }

    private group(slots: Slot[], locale: Locale): HTMLElement {
        const sorted = slots
            .slice()
            .sort(
                (a, b) => (a.expiresInDays ?? 1e9) - (b.expiresInDays ?? 1e9),
            ); // earliest expiry first (FEFO)
        const first = sorted[0].pallet!;
        const open = this.expanded.has(first.productId);
        const head = el(
            'button',
            {
                class: 'wms-pgroup__head',
                attrs: { type: 'button', 'aria-expanded': String(open) },
            },
            el(
                'span',
                {
                    class: 'wms-pgroup__icon',
                    vars: { '--wms-hue': productHue(first.productId) },
                },
                icon('box'),
            ),
            el(
                'span',
                { class: 'wms-pgroup__title' },
                el(
                    'span',
                    { class: 'wms-pgroup__name' },
                    el('b', {
                        class: 'wms-bidi',
                        text: palletName(first, locale),
                    }),
                    first.productActive
                        ? null
                        : productStatusBadge(false, (key) => this.t(key)),
                ),
                el('small', {
                    text: this.t('pallets', { count: sorted.length }),
                }),
            ),
        );

        this.on(head, 'click', () => {
            if (this.expanded.has(first.productId)) {
                this.expanded.delete(first.productId);
            } else {
                this.expanded.add(first.productId);
            }

            this.state = this.slice.get();
            this.render();
        });

        return el(
            'div',
            { class: `wms-pgroup${open ? ' is-open' : ''}` },
            head,
            el(
                'div',
                { class: 'wms-pgroup__locs' },
                ...sorted.map((slot) => {
                    const expiry = expiryText(slot, locale, (k, p) =>
                        this.t(k, p),
                    );
                    const view = el('button', {
                        class: 'wms-link',
                        attrs: { type: 'button' },
                        text: this.t('viewOnMap'),
                    });

                    this.on(view, 'click', () => this.intents.viewOnMap(slot));

                    return el(
                        'div',
                        { class: 'wms-loc' },
                        slotCode(slot.code),
                        stateDot(slot.state),
                        slot.active ? null : tag(this.t('outOfService'), 'off'),
                        el('span', {
                            class: `wms-loc__date wms-exp wms-exp--${expiry?.tone ?? 'ok'}`,
                            text: formatDate(
                                slot.pallet!.expirationDate,
                                locale,
                            ),
                        }),
                        view,
                    );
                }),
            ),
        );
    }
}
