import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    show as helpShow,
    index as helpIndex,
} from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import CellStateLegend from '@/components/CellStateLegend.vue';
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

    it.each(['views', 'legend', 'search', 'walking', 'sharing'])(
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
