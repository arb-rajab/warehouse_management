import { afterEach, describe, expect, it } from 'vitest';
import { mountWarehouseSwitcherPreview } from './preview';

afterEach(() => {
    document.body.innerHTML = '';
});

const options = [
    { id: 1, name: 'Main warehouse' },
    { id: 2, name: 'Annex' },
];

function mount(locale: 'en' | 'ar' = 'en') {
    const host = document.createElement('div');

    document.body.append(host);

    const preview = mountWarehouseSwitcherPreview(host, {
        locale,
        messages: {
            warehouseLabel: locale === 'ar' ? 'المستودع' : 'Warehouse',
            warehousesAll: locale === 'ar' ? 'كل المستودعات' : 'All warehouses',
            warehouseApply: locale === 'ar' ? 'تطبيق' : 'Apply',
        },
        options,
        selected: [1],
    });

    return { host, preview };
}

describe('mountWarehouseSwitcherPreview', () => {
    it("shows the map's own switcher held open, in the brand pill, with the shown warehouse ticked", () => {
        const { host, preview } = mount();
        const root = host.querySelector('.wms.wms--preview')!;

        expect(root.getAttribute('dir')).toBe('ltr');
        expect(root.querySelector('svg defs')).not.toBeNull();
        expect(
            root.querySelector('.wms-brand.has-warehouses .wms-warehouses'),
        ).not.toBeNull();
        expect(
            root.querySelector('.wms-warehouses__trigger')?.textContent,
        ).toBe('Main warehouse');
        expect(
            root.querySelector<HTMLElement>('.wms-warehouses__panel')!.hidden,
        ).toBe(false);
        expect(
            [...root.querySelectorAll('.wms-warehouses__option')].map((o) => [
                o.textContent,
                o.getAttribute('aria-checked'),
            ]),
        ).toEqual([
            ['All warehouses', 'false'],
            ['Main warehouse', 'true'],
            ['Annex', 'false'],
        ]);
        expect(root.querySelector('.wms-warehouses__apply')?.textContent).toBe(
            'Apply',
        );

        // a click elsewhere on the help page leaves it open
        document.body.dispatchEvent(
            new MouseEvent('pointerdown', { bubbles: true }),
        );
        expect(
            root.querySelector<HTMLElement>('.wms-warehouses__panel')!.hidden,
        ).toBe(false);

        preview.dispose();
        expect(host.children).toHaveLength(0);
    });

    it('mirrors in Arabic', () => {
        const { host } = mount('ar');
        const root = host.querySelector('.wms')!;

        expect(root.getAttribute('dir')).toBe('rtl');
        expect(root.getAttribute('lang')).toBe('ar');
        expect(root.querySelector('.wms-warehouses__option')?.textContent).toBe(
            'كل المستودعات',
        );
    });
});
