import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    show as helpShow,
    index as helpIndex,
} from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as warehousesIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Warehouses from './Warehouses.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: () => ({ get: vi.fn() }),
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: helpShow.url({ topic: 'warehouses' }),
        props: defaultAuthProps(),
    });

    return mount(Warehouses);
}

describe('Help Warehouses', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.warehouses.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.warehouses.title'));
    });

    it('renders every section heading and body', () => {
        const wrapper = mountPage();

        const sections = [
            'grid',
            'letters',
            'creating',
            'filtering',
            'deleting',
        ];
        expect(wrapper.findAll('h2').map((h2) => h2.text())).toEqual(
            sections.map((section) => t(`help.warehouses.${section}.heading`)),
        );

        for (const section of sections) {
            expect(wrapper.text()).toContain(
                t(`help.warehouses.${section}.body`),
            );
        }
    });

    it('previews the add button, the warehouse form fields and the warehouse filter', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('warehouses.index.addWarehouse'));
        expect(wrapper.find('#help-warehouse-create-name').exists()).toBe(true);
        expect(wrapper.find('#help-warehouse-create-code').exists()).toBe(true);
        expect(wrapper.find('#help-warehouse-filter').exists()).toBe(true);
    });

    it('previews the delete button and the "Has rows" notice', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('warehouses.index.delete'));
        expect(wrapper.text()).toContain(t('warehouses.index.hasRows'));
    });

    it('links to the warehouses page', () => {
        const wrapper = mountPage();

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === t('warehouses.index.title'));
        expect(link?.attributes('href')).toBe(warehousesIndex.url());
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe(helpIndex.url());
    });
});
