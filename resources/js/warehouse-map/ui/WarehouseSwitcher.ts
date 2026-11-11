import type { WarehouseOption } from '../domain/types';
import {
    normalizeWarehouseIds,
    sameWarehouseIds,
    warehouseSelectionLabel,
} from '../domain/warehouseSelection';
import type { WarehouseSelection } from '../domain/warehouseSelection';
import { el } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface WarehouseSwitcherState {
    options: readonly WarehouseOption[];
    /** The warehouses shown now (`null`: every warehouse). */
    selected: WarehouseSelection;
}

export interface WarehouseSwitcherIntents {
    /** Asks the host for these warehouses (`null`: every warehouse). */
    apply(ids: WarehouseSelection): void;
}

const NAVIGATION_KEYS = ['ArrowDown', 'ArrowUp', 'Home', 'End'];
const ALL = 'all';

/**
 * The toolbar's warehouse switcher: a chip naming the warehouses on the map
 * that opens a menu of "All warehouses" and every warehouse, each one ticked
 * or not. The ticks are a draft: closing the menu (Apply, a tap or click
 * outside, the chip again, or Tab away) asks the host for them when they
 * differ from what is shown; Escape closes without asking. The arrows, Home
 * and End move between the entries and no key reaches the map's shortcuts.
 * The host owns the URL; the map only reports the choice.
 */
export class WarehouseSwitcher extends SliceView<WarehouseSwitcherState> {
    readonly element = el('div', { class: 'wms-warehouses' });
    private readonly trigger = el('button', {
        class: 'wms-chip wms-warehouses__trigger',
        attrs: {
            type: 'button',
            'aria-haspopup': 'menu',
            'aria-expanded': 'false',
        },
    });
    private readonly triggerLabel = el('span', {
        class: 'wms-warehouses__label wms-bidi',
    });
    private readonly panel = el('div', {
        class: 'wms-warehouses__panel',
        attrs: { hidden: true },
    });
    private readonly headLabel = el('span');
    private readonly headValue = el('b', { class: 'wms-bidi' });
    private readonly list = el('div', {
        class: 'wms-warehouses__list',
        attrs: { role: 'menu' },
    });
    private readonly applyButton = el('button', {
        class: 'wms-btn wms-btn--primary wms-btn--sm wms-warehouses__apply',
        attrs: { type: 'button' },
    });
    private draft = new Set<number>();
    private open = false;
    /** The help page's preview stays open whatever is clicked. */
    private pinned = false;

    constructor(
        slice: ConstructorParameters<
            typeof SliceView<WarehouseSwitcherState>
        >[0],
        i18n: ConstructorParameters<
            typeof SliceView<WarehouseSwitcherState>
        >[1],
        private readonly intents: WarehouseSwitcherIntents,
    ) {
        super(slice, i18n);
    }

    get isOpen(): boolean {
        return this.open;
    }

    /** Opens the menu with the shown warehouses ticked, or closes it (applying the ticks unless `apply` is false). */
    setOpen(open: boolean, { apply = true, focus = true } = {}): void {
        if (open === this.open || (!open && this.pinned)) {
            return;
        }

        this.open = open;
        this.panel.hidden = !open;
        this.element.classList.toggle('is-open', open);
        this.trigger.setAttribute('aria-expanded', String(open));

        if (open) {
            this.draft = new Set(this.state.selected ?? []);
            this.renderList();

            if (focus) {
                (
                    this.items().find(
                        (item) => item.getAttribute('aria-checked') === 'true',
                    ) ?? this.items()[0]
                )?.focus();
            }

            return;
        }

        if (apply) {
            const ids = normalizeWarehouseIds(this.draft, this.state.options);

            if (!sameWarehouseIds(ids, this.state.selected)) {
                this.intents.apply(ids);
            }
        }
    }

    /** Shows the menu open for good (the help page's preview). */
    pinOpen(): void {
        this.setOpen(true, { focus: false });
        this.pinned = true;
    }

    protected build(): void {
        this.trigger.append(
            icon('warehouse', 'wms-warehouses__icon'),
            this.triggerLabel,
            icon('down', 'wms-warehouses__chevron'),
        );
        this.panel.append(
            el(
                'div',
                { class: 'wms-warehouses__head' },
                this.headLabel,
                this.headValue,
            ),
            this.list,
            el('div', { class: 'wms-warehouses__foot' }, this.applyButton),
        );
        this.element.append(this.trigger, this.panel);

        this.on(this.trigger, 'click', () => this.setOpen(!this.open));
        this.on(this.applyButton, 'click', () => {
            this.setOpen(false);
            this.trigger.focus();
        });
        this.on(this.element, 'keydown', (e: KeyboardEvent) =>
            this.onKeydown(e),
        );
        this.on(this.element, 'focusout', (e: FocusEvent) => {
            const next = e.relatedTarget as Node | null;

            if (this.open && next && !this.element.contains(next)) {
                this.setOpen(false);
            }
        });
        this.on(this.element.ownerDocument, 'pointerdown', (e: Event) => {
            if (this.open && !this.element.contains(e.target as Node)) {
                this.setOpen(false);
            }
        });
    }

    protected render(): void {
        const label = warehouseSelectionLabel(
            this.state.options,
            this.state.selected,
            this.t.bind(this),
        );

        this.triggerLabel.textContent = label;
        this.trigger.title = this.t('warehouseSwitch');
        this.trigger.setAttribute(
            'aria-label',
            `${this.t('warehouseLabel')}: ${label}`,
        );
        this.headLabel.textContent = `${this.t('warehouseLabel')}: `;
        this.headValue.textContent = label;
        this.list.setAttribute('aria-label', this.t('warehouseSwitch'));
        this.applyButton.textContent = this.t('warehouseApply');

        if (this.open) {
            this.renderList();
        }
    }

    private renderList(): void {
        const focused = this.focusedKey();

        this.list.replaceChildren(
            this.item(ALL, this.t('warehousesAll')),
            el('hr', { class: 'wms-warehouses__rule' }),
            ...this.state.options.map((o) => this.item(String(o.id), o.name)),
        );
        this.syncTicks();

        if (focused !== null) {
            this.items()
                .find((item) => item.dataset.warehouseId === focused)
                ?.focus();
        }
    }

    private item(key: string, name: string): HTMLButtonElement {
        const button = el(
            'button',
            {
                class: 'wms-warehouses__option',
                attrs: { type: 'button', role: 'menuitemcheckbox' },
                data: { 'warehouse-id': key },
            },
            el('span', { class: 'wms-warehouses__mark' }, icon('check')),
            el('span', { class: 'wms-warehouses__name wms-bidi', text: name }),
        );

        this.on(button, 'click', () => this.toggle(key));

        return button;
    }

    private toggle(key: string): void {
        if (key === ALL) {
            this.draft.clear();
        } else {
            const id = Number(key);

            if (!this.draft.delete(id)) {
                this.draft.add(id);
            }
        }

        this.syncTicks();
    }

    /** Ticks follow the draft; "All warehouses" is ticked when nothing else is. */
    private syncTicks(): void {
        for (const item of this.items()) {
            const key = item.dataset.warehouseId;
            const checked =
                key === ALL
                    ? this.draft.size === 0
                    : this.draft.has(Number(key));

            item.setAttribute('aria-checked', String(checked));
        }
    }

    private items(): HTMLButtonElement[] {
        return [
            ...this.list.querySelectorAll<HTMLButtonElement>(
                '.wms-warehouses__option',
            ),
        ];
    }

    private focusedKey(): string | null {
        const active = this.element.ownerDocument.activeElement;

        return active instanceof HTMLElement && this.list.contains(active)
            ? (active.dataset.warehouseId ?? null)
            : null;
    }

    /** Escape closes without applying, the arrows / Home / End move between the entries; nothing leaks to the map's own shortcuts. */
    private onKeydown(e: KeyboardEvent): void {
        if (!this.open) {
            // the down arrow on the chip opens the menu, like a select
            if (e.key === 'ArrowDown' && e.target === this.trigger) {
                e.preventDefault();
                e.stopPropagation();
                this.setOpen(true);
            }

            return;
        }

        e.stopPropagation();

        if (e.key === 'Escape') {
            e.preventDefault();
            this.setOpen(false, { apply: false });
            this.trigger.focus();

            return;
        }

        if (!NAVIGATION_KEYS.includes(e.key)) {
            return;
        }

        const items = this.items();

        if (items.length === 0) {
            return;
        }

        e.preventDefault();

        const index = items.indexOf(e.target as HTMLButtonElement);
        const last = items.length - 1;
        const next = {
            ArrowDown: index < 0 ? 0 : (index + 1) % items.length,
            ArrowUp:
                index < 0 ? last : (index - 1 + items.length) % items.length,
            Home: 0,
            End: last,
        }[e.key] as number;

        items[next].focus();
    }
}
