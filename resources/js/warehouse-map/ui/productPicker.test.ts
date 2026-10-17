import { afterEach, describe, expect, it, vi } from 'vitest';
import type { Locale, ProductOption } from '../domain/types';
import { click, fakeSlice, host, keyI18n } from '../testing/ui';
import { ProductPicker } from './ProductPicker';
import type { ProductPickerState } from './ProductPicker';

afterEach(() => {
    document.body.innerHTML = '';
});

const OPTIONS: ProductOption[] = [
    {
        id: 4,
        name: 'White Sugar',
        arName: 'سكر أبيض',
        active: true,
        pallets: 3,
    },
    {
        id: 1,
        name: 'Basmati Rice',
        arName: 'أرز بسمتي',
        active: true,
        pallets: 1,
    },
    { id: 9, name: 'Old Oil', arName: '', active: false, pallets: 2 },
];

function picker(selected: number[] = [], locale: Locale = 'en') {
    const root = host();
    const slice = fakeSlice<ProductPickerState>({
        options: OPTIONS,
        selected,
        locale,
    });
    const intents = { toggle: vi.fn(), clear: vi.fn() };
    const view = new ProductPicker(slice, keyI18n(), intents);

    view.mount();
    root.append(view.element);

    const trigger = root.querySelector<HTMLButtonElement>(
        '.wms-products__trigger',
    )!;
    const panel = root.querySelector<HTMLElement>('.wms-products__panel')!;
    const search = root.querySelector<HTMLInputElement>(
        '.wms-products__search',
    )!;
    const names = () =>
        [...root.querySelectorAll('.wms-products__name b')].map(
            (b) => b.textContent,
        );
    const box = (id: number) =>
        root.querySelector<HTMLInputElement>(`[data-product-id="${id}"]`)!;
    const key = (target: Element, name: string) =>
        target.dispatchEvent(
            new KeyboardEvent('keydown', { key: name, bubbles: true }),
        );

    return {
        view,
        root,
        slice,
        intents,
        trigger,
        panel,
        search,
        names,
        box,
        key,
    };
}

describe('ProductPicker', () => {
    it('names the selection on its button: all, one product, or a count', () => {
        const none = picker();

        expect(none.trigger.textContent).toBe('productsAll');
        expect(none.trigger.classList.contains('is-on')).toBe(false);
        expect(none.trigger.getAttribute('aria-label')).toBe('productsLabel');

        none.slice.set({ selected: [1] });
        expect(none.trigger.textContent).toBe('Basmati Rice');
        expect(none.trigger.classList.contains('is-on')).toBe(true);

        none.slice.set({ selected: [1, 4] });
        expect(none.trigger.textContent).toBe('products(2)');

        none.slice.set({ selected: [77] });
        expect(none.trigger.textContent).toBe('#77');
        none.view.dispose();
    });

    it('opens a checklist of every product, sorted by the shown name, with its id, pallet count and status', () => {
        const { view, root, trigger, panel, search, names } = picker();

        expect(panel.hidden).toBe(true);
        click(trigger);

        expect(panel.hidden).toBe(false);
        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(search);
        expect(search.placeholder).toBe('productsSearchPh');
        expect(names()).toEqual(['Basmati Rice', 'Old Oil', 'White Sugar']);

        const rice = root.querySelector('.wms-products__option')!;

        expect(rice.textContent).toContain('#1');
        expect(rice.textContent).toContain('أرز بسمتي');
        expect(rice.textContent).toContain('pallets(1)');
        expect(rice.querySelector('.wms-badge--on')?.textContent).toBe(
            'productActive',
        );
        view.dispose();
    });

    it('marks a product that is no longer sold as inactive', () => {
        const { view, root, trigger } = picker();

        click(trigger);

        const inactive = [
            ...root.querySelectorAll('.wms-products__option'),
        ].find((option) => option.textContent?.includes('Old Oil'))!;

        expect(inactive.classList.contains('is-inactive')).toBe(true);
        expect(inactive.querySelector('.wms-badge--off')?.textContent).toBe(
            'productInactive',
        );
        expect(root.querySelectorAll('.wms-badge--off')).toHaveLength(1);
        view.dispose();
    });

    it('sorts and names by the Arabic name in Arabic', () => {
        const { view, trigger, names } = picker([], 'ar');

        click(trigger);
        expect(names()).toEqual(['أرز بسمتي', 'سكر أبيض', 'Old Oil']);
        view.dispose();
    });

    it('checks and unchecks products, keeping the checked ones pinned on top under "Deselect all"', () => {
        const { view, root, slice, intents, trigger, box, names } = picker();

        click(trigger);
        expect(root.querySelector('.wms-products__head')).toBeNull();

        box(4).click();
        expect(intents.toggle).toHaveBeenCalledWith(4);

        slice.set({ selected: [4] });
        expect(names()).toEqual(['White Sugar', 'Basmati Rice', 'Old Oil']);
        expect(box(4).checked).toBe(true);
        expect(box(1).checked).toBe(false);
        expect(
            root.querySelector('.wms-products__head')?.textContent,
        ).toContain('productsSelected');

        click(root.querySelector('.wms-products__clear'));
        expect(intents.clear).toHaveBeenCalled();
        view.dispose();
    });

    it('keeps the focus on the option that was just toggled', () => {
        const { view, slice, trigger, box } = picker();

        click(trigger);
        box(9).focus();
        slice.set({ selected: [9] });

        expect(document.activeElement).toBe(box(9));
        view.dispose();
    });

    it('narrows the list by name in either language or by id, but never hides a checked product', () => {
        const { view, root, search, trigger, names } = picker([9]);

        click(trigger);

        search.value = 'سكر';
        search.dispatchEvent(new Event('input'));
        expect(names()).toEqual(['Old Oil', 'White Sugar']);

        search.value = '#1';
        search.dispatchEvent(new Event('input'));
        expect(names()).toEqual(['Old Oil', 'Basmati Rice']);

        search.value = 'nothing';
        search.dispatchEvent(new Event('input'));
        expect(names()).toEqual(['Old Oil']);
        expect(root.querySelector('.wms-products__empty')?.textContent).toBe(
            'productsNone',
        );
        view.dispose();
    });

    it('moves between options with the arrows, Home and End, without the keys reaching the map', () => {
        const { view, trigger, search, box, key } = picker();
        const mapKeys = vi.fn();

        window.addEventListener('keydown', mapKeys);
        click(trigger);

        key(search, 'ArrowDown');
        expect(document.activeElement).toBe(box(1));
        key(box(1), 'ArrowDown');
        expect(document.activeElement).toBe(box(9));
        key(box(9), 'End');
        expect(document.activeElement).toBe(box(4));
        key(box(4), 'ArrowDown');
        expect(document.activeElement).toBe(box(1));
        key(box(1), 'ArrowUp');
        expect(document.activeElement).toBe(box(4));
        key(box(4), 'Home');
        expect(document.activeElement).toBe(box(1));
        key(box(1), 'w');

        expect(mapKeys).not.toHaveBeenCalled();
        window.removeEventListener('keydown', mapKeys);
        view.dispose();
    });

    it('closes on Escape, giving the focus back to its button, and on a click outside', () => {
        const { view, trigger, panel, search, key } = picker();
        const mapKeys = vi.fn();

        window.addEventListener('keydown', mapKeys);
        click(trigger);
        key(search, 'Escape');

        expect(panel.hidden).toBe(true);
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger);
        expect(mapKeys).not.toHaveBeenCalled();
        window.removeEventListener('keydown', mapKeys);

        click(trigger);
        document.body.dispatchEvent(
            new Event('pointerdown', { bubbles: true }),
        );
        expect(panel.hidden).toBe(true);
        view.dispose();
    });
});
