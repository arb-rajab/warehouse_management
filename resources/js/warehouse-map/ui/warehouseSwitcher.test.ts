import { afterEach, describe, expect, it, vi } from 'vitest';
import type { WarehouseSelection } from '../domain/warehouseSelection';
import { I18n } from '../i18n/I18n';
import { click, fakeSlice, host, keyI18n } from '../testing/ui';
import { WarehouseSwitcher } from './WarehouseSwitcher';

afterEach(() => {
    document.body.innerHTML = '';
});

const options = [
    { id: 1, name: 'Main' },
    { id: 2, name: 'Annex' },
    { id: 3, name: 'Cold store' },
];

function switcher(selected: WarehouseSelection = null, i18n = keyI18n()) {
    const slice = fakeSlice({ options, selected });
    const intents = { apply: vi.fn() };
    const view = new WarehouseSwitcher(slice, i18n, intents);
    const root = host();

    root.append(view.element);
    view.mount();

    const q = <T extends Element>(selector: string) =>
        view.element.querySelector<T>(selector)!;
    const items = () => [
        ...view.element.querySelectorAll<HTMLButtonElement>(
            '.wms-warehouses__option',
        ),
    ];
    const item = (key: string) =>
        items().find((i) => i.dataset.warehouseId === key)!;
    const ticked = () =>
        items()
            .filter((i) => i.getAttribute('aria-checked') === 'true')
            .map((i) => i.dataset.warehouseId);
    const key = (target: Element, k: string) => {
        const event = new KeyboardEvent('keydown', {
            key: k,
            bubbles: true,
            cancelable: true,
        });

        target.dispatchEvent(event);

        return event;
    };

    return {
        view,
        slice,
        intents,
        trigger: q<HTMLButtonElement>('.wms-warehouses__trigger'),
        panel: q<HTMLElement>('.wms-warehouses__panel'),
        applyButton: q<HTMLButtonElement>('.wms-warehouses__apply'),
        items,
        item,
        ticked,
        key,
    };
}

describe('WarehouseSwitcher', () => {
    it('names the warehouses shown on its chip: all, one by name, or a count', () => {
        const { view, slice, trigger } = switcher();

        expect(trigger.textContent).toBe('warehousesAll');
        expect(trigger.getAttribute('aria-label')).toBe(
            'warehouseLabel: warehousesAll',
        );
        expect(trigger.title).toBe('warehouseSwitch');
        expect(trigger.getAttribute('aria-haspopup')).toBe('menu');

        slice.set({ selected: [2] });
        expect(trigger.textContent).toBe('Annex');
        expect(trigger.getAttribute('aria-label')).toBe(
            'warehouseLabel: Annex',
        );

        slice.set({ selected: [1, 3] });
        expect(trigger.textContent).toBe('warehousesCount(2)');
        view.dispose();
    });

    it('opens a menu of "All warehouses" and every warehouse, the shown ones ticked and the first of them focused', () => {
        const { view, trigger, panel, items, ticked } = switcher([2]);

        expect(panel.hidden).toBe(true);
        click(trigger);

        expect(panel.hidden).toBe(false);
        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(items().map((i) => i.textContent)).toEqual([
            'warehousesAll',
            'Main',
            'Annex',
            'Cold store',
        ]);
        expect(
            items().every((i) => i.getAttribute('role') === 'menuitemcheckbox'),
        ).toBe(true);
        expect(
            panel.querySelector('[role="menu"]')?.getAttribute('aria-label'),
        ).toBe('warehouseSwitch');
        expect(ticked()).toEqual(['2']);
        expect(document.activeElement).toBe(items()[2]);
        expect(panel.querySelector('.wms-warehouses__head')?.textContent).toBe(
            'warehouseLabel: Annex',
        );
        view.dispose();
    });

    it('ticks several warehouses and asks for them once, on Apply', () => {
        const { view, trigger, panel, item, ticked, applyButton, intents } =
            switcher();

        click(trigger);
        expect(ticked()).toEqual(['all']);

        click(item('3'));
        click(item('1'));
        expect(ticked()).toEqual(['1', '3']);
        expect(intents.apply).not.toHaveBeenCalled();

        click(applyButton);

        expect(intents.apply).toHaveBeenCalledTimes(1);
        expect(intents.apply).toHaveBeenCalledWith([1, 3]);
        expect(panel.hidden).toBe(true);
        expect(document.activeElement).toBe(trigger);
        view.dispose();
    });

    it('applies the ticks when the menu is closed by a tap outside or the chip', () => {
        const outside = switcher([1]);

        click(outside.trigger);
        click(outside.item('2'));
        document.body.dispatchEvent(
            new MouseEvent('pointerdown', { bubbles: true }),
        );

        expect(outside.panel.hidden).toBe(true);
        expect(outside.intents.apply).toHaveBeenCalledWith([1, 2]);
        outside.view.dispose();

        const chip = switcher([1]);

        click(chip.trigger);
        click(chip.item('1'));
        click(chip.item('3'));
        click(chip.trigger);

        expect(chip.intents.apply).toHaveBeenCalledWith([3]);
        chip.view.dispose();
    });

    it('"All warehouses" clears the ticks and asks for every warehouse', () => {
        const { view, trigger, item, ticked, applyButton, intents } = switcher([
            1, 2,
        ]);

        click(trigger);
        click(item('all'));
        expect(ticked()).toEqual(['all']);

        click(applyButton);
        expect(intents.apply).toHaveBeenCalledWith(null);
        view.dispose();
    });

    it('treats every warehouse ticked as all of them', () => {
        const { view, trigger, item, applyButton, intents } = switcher([1]);

        click(trigger);
        click(item('2'));
        click(item('3'));
        click(applyButton);

        expect(intents.apply).toHaveBeenCalledWith(null);
        view.dispose();
    });

    it('asks for nothing when the ticks match what is shown', () => {
        const { view, trigger, item, applyButton, intents } = switcher([1, 2]);

        click(trigger);
        click(item('1'));
        click(item('1'));
        click(applyButton);

        expect(intents.apply).not.toHaveBeenCalled();
        view.dispose();
    });

    it('Escape closes without asking and gives the focus back to the chip; no key reaches the map', () => {
        const { view, trigger, panel, item, intents, key } = switcher();
        const mapKeys = vi.fn();

        window.addEventListener('keydown', mapKeys);
        click(trigger);
        click(item('2'));
        key(item('2'), 't');
        expect(mapKeys).not.toHaveBeenCalled();

        const escape = key(item('2'), 'Escape');

        expect(escape.defaultPrevented).toBe(true);
        expect(panel.hidden).toBe(true);
        expect(document.activeElement).toBe(trigger);
        expect(intents.apply).not.toHaveBeenCalled();
        expect(mapKeys).not.toHaveBeenCalled();
        window.removeEventListener('keydown', mapKeys);
        view.dispose();
    });

    it('moves between the entries with the arrows, Home and End, and opens from the chip with the down arrow', () => {
        const { view, trigger, panel, items, key } = switcher();

        key(trigger, 'ArrowDown');
        expect(panel.hidden).toBe(false);
        expect(document.activeElement).toBe(items()[0]);

        key(items()[0], 'ArrowDown');
        expect(document.activeElement).toBe(items()[1]);
        key(items()[1], 'End');
        expect(document.activeElement).toBe(items()[3]);
        key(items()[3], 'ArrowDown');
        expect(document.activeElement).toBe(items()[0]);
        key(items()[0], 'ArrowUp');
        expect(document.activeElement).toBe(items()[3]);
        key(items()[3], 'Home');
        expect(document.activeElement).toBe(items()[0]);
        view.dispose();
    });

    it('applies when the focus leaves the menu with Tab', () => {
        const { view, trigger, item, intents, panel } = switcher();
        const elsewhere = document.createElement('button');

        document.body.append(elsewhere);
        click(trigger);
        click(item('2'));
        item('2').dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: elsewhere,
            }),
        );

        expect(panel.hidden).toBe(true);
        expect(intents.apply).toHaveBeenCalledWith([2]);
        view.dispose();
    });

    it('keeps the draft while the language changes', () => {
        const i18n = new I18n('en', {
            warehousesAll: 'All warehouses',
            warehouseLabel: 'Warehouse',
            warehouseApply: 'Apply',
        });
        const { view, trigger, item, ticked, applyButton } = switcher(
            null,
            i18n,
        );

        click(trigger);
        click(item('2'));
        i18n.setMessages('ar', {
            warehousesAll: 'كل المستودعات',
            warehouseLabel: 'المستودع',
            warehouseApply: 'تطبيق',
        });

        expect(ticked()).toEqual(['2']);
        expect(item('all').textContent).toBe('كل المستودعات');
        expect(applyButton.textContent).toBe('تطبيق');
        view.dispose();
    });

    it('stays open for the help page preview, whatever is clicked', () => {
        const { view, panel, intents } = switcher([1]);

        view.pinOpen();
        document.body.dispatchEvent(
            new MouseEvent('pointerdown', { bubbles: true }),
        );

        expect(panel.hidden).toBe(false);
        expect(intents.apply).not.toHaveBeenCalled();
        view.dispose();
    });
});
