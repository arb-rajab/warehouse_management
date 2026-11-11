import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    show as helpShow,
    index as helpIndex,
} from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import WarehouseMap from './WarehouseMap.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: helpShow.url({ topic: 'warehouse-map' }),
        props: defaultAuthProps(),
    });

    return mount(WarehouseMap);
}

describe('Help Warehouse map', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.warehouseMap.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.warehouseMap.title'));
    });

    it.each(['views', 'legend', 'search', 'warehouses', 'walking', 'sharing'])(
        'explains %s',
        (section) => {
            const wrapper = mountPage();

            expect(wrapper.text()).toContain(
                t(`help.warehouseMap.${section}.heading`),
            );
            expect(wrapper.text()).toContain(
                t(`help.warehouseMap.${section}.body`),
            );
        },
    );

    it('previews the shared colour legend', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(CellStateLegend).exists()).toBe(true);
    });

    it("previews the map's own warehouse switcher, held open, without letting it take the focus", () => {
        const wrapper = mountPage();
        const host = wrapper.get('[data-testid="warehouse-switcher-preview"]');
        const preview = wrapper
            .findAllComponents(HelpUiPreview)
            .find((p) =>
                p.find('[data-testid="warehouse-switcher-preview"]').exists(),
            );

        expect(preview?.attributes('inert')).toBeDefined();
        expect(host.find('.wms.wms--preview').exists()).toBe(true);
        expect(host.get('.wms-warehouses__trigger').text()).toBe(
            t('help.warehouseMap.warehouses.exampleMain'),
        );
        expect(
            host.findAll('.wms-warehouses__option').map((o) => o.text()),
        ).toEqual([
            t('warehouseMap.warehousesAll'),
            t('help.warehouseMap.warehouses.exampleMain'),
            t('help.warehouseMap.warehouses.exampleAnnex'),
            t('help.warehouseMap.warehouses.exampleCold'),
        ]);
        expect(host.get('.wms-warehouses__panel').isVisible()).toBe(true);
        expect(host.get('.wms-warehouses__apply').text()).toBe(
            t('warehouseMap.warehouseApply'),
        );

        wrapper.unmount();
        expect(host.element.children).toHaveLength(0);
    });

    it('links to the warehouse map', () => {
        const wrapper = mountPage();

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === t('nav.warehouseMap'));
        expect(link?.attributes('href')).toBe(warehouseMapIndex.url());
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe(helpIndex.url());
    });
});
