import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Products from './Products.vue';

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
        url: '/admin/help/products',
        props: defaultAuthProps(),
    });

    return mount(Products);
}

describe('Help Products', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.products.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.products.title'));
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        const headings = wrapper.findAll('h2').map((h2) => h2.text());
        expect(headings).toEqual([
            t('help.products.defaultBoxes.heading'),
            t('help.products.settingBoxes.heading'),
            t('help.products.exportingQr.heading'),
        ]);
    });

    it('explains why products default to 50 boxes per pallet', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.products.defaultBoxes.body'));
    });

    it('explains how to set the real box count', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.products.settingBoxes.body'));
    });

    it('explains how to print a products QR label', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.products.exportingQr.body'));
    });

    it('renders a preview of the products table with its real column headers and an example row', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('products.columns.product'));
        expect(wrapper.text()).toContain(t('products.columns.boxesPerPallet'));
        expect(wrapper.text()).toContain(t('products.columns.full'));
        expect(wrapper.text()).toContain(t('products.columns.opened'));
        expect(wrapper.text()).toContain(t('products.columns.expired'));
        expect(wrapper.text()).toContain(
            t('products.columns.expiringSoon', { days: 45 }),
        );
        expect(wrapper.text()).toContain(t('products.columns.activityToday'));
        expect(wrapper.text()).toContain(t('products.columns.activityWeek'));

        const table = wrapper.find('table');
        expect(table.text()).toContain('Example product');
        expect(table.text()).toContain('50');
    });

    it('renders a preview of the boxes-per-pallet control', () => {
        const wrapper = mountPage();

        const preview = wrapper
            .findAll('button')
            .find((b) => b.text() === '50');
        expect(preview).toBeTruthy();
    });

    it('renders a preview of the boxes-per-pallet edit dialog fields', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('products.columns.boxesPerPallet'));
        expect(wrapper.text()).toContain(
            t('products.boxesPerPalletLabel', {
                product: t('products.columns.product'),
            }),
        );

        const input = wrapper.get('#help-products-box-count');
        expect((input.element as HTMLInputElement).value).toBe('50');
    });

    it('renders a preview of the export-QR control', () => {
        const wrapper = mountPage();

        const preview = wrapper
            .findAll('span')
            .find((s) => s.attributes('title') === t('products.columns.qr'));
        expect(preview).toBeTruthy();
    });

    it('links to the products page', () => {
        const wrapper = mountPage();

        const productsLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('products.title'));
        expect(productsLink?.attributes('href')).toBe('/admin/products');
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});
