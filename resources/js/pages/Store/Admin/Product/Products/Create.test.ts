import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    inHouse as inHouseProducts,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreProductFormFields from '@/components/StoreProductFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeDigitalProductTax, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({
        errors: { name: 'Product name is required' } as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        useHttp: () => ({
            processing: false,
            get: vi.fn(),
            post: vi.fn(),
            cancel: vi.fn(),
        }),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});

const props = {
    categories: [{ id: 1, name: 'Pantry' }],
    brands: [{ id: 5, name: 'Olivia' }],
    colors: [{ id: 3, name: 'Red', code: '#FF0000' }],
    attributes: [{ id: 1, name: 'Size' }],
    flashDeals: [{ id: 9, name: 'Autumn deals' }],
    taxes: [storeDigitalProductTax()],
    sections: {
        shipping: true,
        productWiseShipping: true,
        flashDeals: true,
        vat: true,
        cashOnDelivery: true,
    },
};
const wrappers: VueWrapper[] = [];

function createPage(): VueWrapper {
    const wrapper = mount(Create, { props });
    wrappers.push(wrapper);

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        url: '/temp/admin/products/create',
        props: {
            ...defaultAuthProps(),
            store: storeSharedProps({
                permissions: ['add_new_product', 'view_uploads'],
            }),
        },
    });
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('Store/Admin/Product/Products/Create', () => {
    it.each(['en', 'ar'] as const)(
        'labels the page and both save buttons in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = createPage();

            expect(wrapper.get('h1').text()).toBe(
                t('store.products.form.createTitle'),
            );
            expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
                label: t('store.products.form.savePublish'),
                processingLabel: t('store.products.form.saving'),
            });
            expect(wrapper.get('button[name="button"]').text()).toBe(
                t('store.products.form.saveUnpublish'),
            );
        },
    );

    it('posts to the typed store action inside the store shell and returns to the in-house list', () => {
        const wrapper = createPage();

        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            action: store(),
            cancelHref: inHouseProducts(),
            layout: StoreAdminLayout,
            wide: true,
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    });

    it('passes every option, the sections and the form errors to the fields', () => {
        const wrapper = createPage();

        expect(wrapper.getComponent(StoreProductFormFields).props()).toEqual({
            ...props,
            errors: { name: 'Product name is required' },
        });
        expect(wrapper.text()).toContain('Product name is required');
    });

    it('submits the unpublish choice with the legacy button value; publishing sends none', () => {
        const wrapper = createPage();
        const unpublish = wrapper.get('button[name="button"]');

        expect(unpublish.attributes()).toMatchObject({
            type: 'submit',
            value: 'unpublish',
        });
        expect(
            wrapper
                .getComponent(SubmitButton)
                .find('button')
                .attributes('name'),
        ).toBeUndefined();
        expect(unpublish.element.closest('form')).toBe(
            wrapper.getComponent(SubmitButton).element.closest('form'),
        );
    });
});
