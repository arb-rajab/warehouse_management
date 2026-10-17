import { filterProductOptions } from '../domain/productOptions';
import type { Locale, ProductOption } from '../domain/types';
import { palletName } from '../i18n/format';
import { el, replaceChildrenOf } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface ProductPickerState {
    options: readonly ProductOption[];
    selected: readonly number[];
    locale: Locale;
}

export interface ProductPickerIntents {
    toggle(id: number): void;
    clear(): void;
}

const NAVIGATION_KEYS = ['ArrowDown', 'ArrowUp', 'Home', 'End'];

/**
 * The product filter: a button that opens a searchable checklist of the
 * products the warehouse holds, checked ones pinned on top under "Deselect
 * all" — the same model as the panel's other product filters. A slot matches
 * when it holds any checked product.
 */
export class ProductPicker extends SliceView<ProductPickerState> {
    readonly element = el('div', { class: 'wms-products' });
    private readonly trigger = el('button', {
        class: 'wms-chip wms-products__trigger',
        attrs: {
            type: 'button',
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false',
        },
    });
    private readonly triggerLabel = el('span', {
        class: 'wms-products__label',
    });
    private readonly panel = el('div', {
        class: 'wms-products__panel',
        attrs: { hidden: true },
    });
    private readonly search = el('input', {
        class: 'wms-products__search',
        attrs: {
            type: 'search',
            autocomplete: 'off',
            spellcheck: 'false',
            dir: 'auto',
        },
    });
    private readonly list = el('div', {
        class: 'wms-products__list',
        attrs: { role: 'listbox', 'aria-multiselectable': 'true' },
    });
    private open = false;

    constructor(
        slice: ConstructorParameters<typeof SliceView<ProductPickerState>>[0],
        i18n: ConstructorParameters<typeof SliceView<ProductPickerState>>[1],
        private readonly intents: ProductPickerIntents,
    ) {
        super(slice, i18n);
    }

    get isOpen(): boolean {
        return this.open;
    }

    setOpen(open: boolean): void {
        this.open = open;
        this.panel.hidden = !open;
        this.element.classList.toggle('is-open', open);
        this.trigger.setAttribute('aria-expanded', String(open));

        if (open) {
            this.search.value = '';
            this.renderList();
            this.search.focus();
        }
    }

    protected build(): void {
        this.trigger.append(
            icon('box'),
            this.triggerLabel,
            icon('down', 'wms-products__chevron'),
        );
        this.panel.append(
            el(
                'label',
                { class: 'wms-products__field' },
                icon('search'),
                this.search,
            ),
            this.list,
        );
        this.element.append(this.trigger, this.panel);

        this.on(this.trigger, 'click', () => this.setOpen(!this.open));
        this.on(this.search, 'input', () => this.renderList());
        this.on(this.element, 'keydown', (e: KeyboardEvent) =>
            this.onKeydown(e),
        );
        this.on(this.element.ownerDocument, 'pointerdown', (e: Event) => {
            if (this.open && !this.element.contains(e.target as Node)) {
                this.setOpen(false);
            }
        });
    }

    protected render(): void {
        const { selected } = this.state;

        this.triggerLabel.textContent = this.triggerText();
        this.trigger.classList.toggle('is-on', selected.length > 0);
        this.trigger.setAttribute('aria-label', this.t('productsLabel'));
        this.trigger.title = this.t('productsLabel');
        this.search.placeholder = this.t('productsSearchPh');
        this.search.setAttribute('aria-label', this.t('productsSearchPh'));
        this.list.setAttribute('aria-label', this.t('productsLabel'));

        if (this.open) {
            this.renderList();
        }
    }

    private label(option: ProductOption): string {
        return palletName(
            { productName: option.name, productArName: option.arName },
            this.state.locale,
        );
    }

    private triggerText(): string {
        const { selected, options } = this.state;

        if (selected.length === 0) {
            return this.t('productsAll');
        }

        if (selected.length > 1) {
            return this.t('products', { count: selected.length });
        }

        const option = options.find((o) => o.id === selected[0]);

        return option ? this.label(option) : `#${selected[0]}`;
    }

    private renderList(): void {
        const { options, selected, locale } = this.state;
        const checked = new Set(selected);
        const focusedId = this.focusedOptionId();
        const sorted = [...options].sort(
            (a, b) =>
                this.label(a).localeCompare(this.label(b), locale) ||
                a.id - b.id,
        );
        const pinned = sorted.filter((o) => checked.has(o.id));
        const results = filterProductOptions(sorted, this.search.value).filter(
            (o) => !checked.has(o.id),
        );

        replaceChildrenOf(
            this.list,
            selected.length > 0 ? this.selectedHeader() : null,
            ...pinned.map((o) => this.option(o, true)),
            pinned.length > 0
                ? el('hr', { class: 'wms-products__rule' })
                : null,
            ...results.map((o) => this.option(o, false)),
            results.length === 0
                ? el('p', {
                      class: 'wms-products__empty',
                      text: this.t('productsNone'),
                  })
                : null,
        );

        if (focusedId !== null) {
            this.checkboxes()
                .find((box) => box.dataset.productId === focusedId)
                ?.focus();
        }
    }

    private selectedHeader(): HTMLElement {
        const clear = el('button', {
            class: 'wms-link wms-products__clear',
            attrs: { type: 'button' },
            text: this.t('productsDeselectAll'),
        });

        this.on(clear, 'click', () => {
            this.intents.clear();
            this.search.focus();
        });

        return el(
            'div',
            { class: 'wms-products__head' },
            el('small', { text: this.t('productsSelected') }),
            clear,
        );
    }

    private option(option: ProductOption, checked: boolean): HTMLElement {
        const box = el('input', {
            class: 'wms-products__check',
            attrs: { type: 'checkbox' },
            data: { 'product-id': option.id },
        });
        const label = this.label(option);
        const otherName = label === option.name ? option.arName : option.name;

        box.checked = checked;
        this.on(box, 'change', () => this.intents.toggle(option.id));

        return el(
            'label',
            {
                class: `wms-products__option${option.active ? '' : ' is-inactive'}`,
                attrs: { role: 'option', 'aria-selected': String(checked) },
            },
            box,
            el(
                'span',
                { class: 'wms-products__text' },
                el(
                    'span',
                    { class: 'wms-products__name' },
                    el('b', { class: 'wms-bidi', text: label }),
                    el('small', { text: `#${option.id}` }),
                    productStatusBadge(option.active, (key) => this.t(key)),
                ),
                otherName && otherName !== label
                    ? el('small', {
                          class: 'wms-products__alt wms-bidi',
                          text: otherName,
                      })
                    : null,
            ),
            el('small', {
                class: 'wms-products__count',
                text: this.t('pallets', { count: option.pallets }),
            }),
        );
    }

    private checkboxes(): HTMLInputElement[] {
        return [
            ...this.list.querySelectorAll<HTMLInputElement>(
                '.wms-products__check',
            ),
        ];
    }

    private focusedOptionId(): string | null {
        const active = this.element.ownerDocument.activeElement;

        return active instanceof HTMLInputElement && this.list.contains(active)
            ? (active.dataset.productId ?? null)
            : null;
    }

    /** Escape closes, the arrows / Home / End move between the options; nothing leaks to the map's own shortcuts. */
    private onKeydown(e: KeyboardEvent): void {
        if (!this.open) {
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            this.setOpen(false);
            this.trigger.focus();

            return;
        }

        if (!NAVIGATION_KEYS.includes(e.key)) {
            e.stopPropagation();

            return;
        }

        const boxes = this.checkboxes();

        if (boxes.length === 0) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const index = boxes.indexOf(e.target as HTMLInputElement);
        const last = boxes.length - 1;
        const next = {
            ArrowDown: index < 0 ? 0 : (index + 1) % boxes.length,
            ArrowUp:
                index < 0 ? last : (index - 1 + boxes.length) % boxes.length,
            Home: 0,
            End: last,
        }[e.key] as number;

        boxes[next].focus();
    }
}

/** "Active" / "Inactive" for a product, the way the panel's product lists mark it. */
export function productStatusBadge(
    active: boolean,
    t: (key: string) => string,
): HTMLElement {
    return el('span', {
        class: `wms-badge wms-badge--${active ? 'on' : 'off'}`,
        text: t(active ? 'productActive' : 'productInactive'),
    });
}
