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

    it('explains why products default to 50 boxes per pallet', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.products.defaultBoxes.body'));
    });

    it('explains how to set the real box count', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.products.settingBoxes.body'));
    });

    it('renders a preview of the boxes-per-pallet control', () => {
        const wrapper = mountPage();

        const preview = wrapper
            .findAll('button')
            .find((b) => b.text() === '50');
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
