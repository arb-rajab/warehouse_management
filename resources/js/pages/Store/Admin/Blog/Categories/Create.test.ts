import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as categoriesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogCategoryController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogCategoryFormFields from '@/components/StoreBlogCategoryFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Page from './Create.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: vi.fn() },
    };
});
function mountPage() {
    return mount(Page, {});
}

describe('Store blog category Create', () => {
    beforeEach(() => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/blog-category',
            props: defaultAuthProps({ store: storeSharedProps() }),
        });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('full mounts the established store form shell with all translated labels', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.blogCategories.create.title'),
            action: store(),
            submitLabel: t('store.blogCategories.create.submit'),
            submittingLabel: t('store.blogCategories.create.submitting'),
            cancelHref: categoriesIndex(),
            layout: StoreAdminLayout,
        });
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.blogCategories.create.title'),
        );
        expect(wrapper.get('h1').text()).toBe(
            t('store.blogCategories.create.title'),
        );
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(
            wrapper.getComponent(StoreBlogCategoryFormFields).props(),
        ).toEqual({ categoryName: undefined, errors: {} });
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.blogCategories.create.submit'),
        );
        const cancel = wrapper
            .findAllComponents(Link)
            .find((link) => link.text() === t('common.cancel'));
        expect(cancel?.props('href')).toEqual(categoriesIndex());
    });
    it('forwards server errors and renders the processing label', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { category_name: 'Invalid name' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper.getComponent(StoreBlogCategoryFormFields).props('errors'),
        ).toEqual({ category_name: 'Invalid name' });
        expect(wrapper.text()).toContain('Invalid name');
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.blogCategories.create.submitting'),
        );
    });
});
