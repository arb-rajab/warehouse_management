import { Form, Head, Link } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as blogsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogFormFields from '@/components/StoreBlogFormFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeBlogCategory,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Page from './Create.vue';

const { usePageMock, useHttpMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
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
        useHttp: useHttpMock,
        router: { post: vi.fn(), get: vi.fn() },
    };
});
const categories = [storeBlogCategory()];

function mountPage() {
    return mount(Page, { props: { categories } });
}

describe('Store blog post Create', () => {
    beforeEach(() => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/blog/create',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['add_blog', 'view_uploads'],
                }),
            }),
        });
        useHttpMock.mockImplementation((data) =>
            createUseHttpStub(data, paginated([])),
        );
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'full mounts the store form shell with the shared editor in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            await flushPromises();
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.blogPosts.create.title'),
                action: store(),
                submitLabel: t('store.blogPosts.create.submit'),
                submittingLabel: t('store.blogPosts.create.submitting'),
                cancelHref: blogsIndex(),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.blogPosts.create.title'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.blogPosts.create.title'),
            );
            expect(wrapper.getComponent(Form).props('action')).toEqual(store());
            expect(wrapper.getComponent(StoreBlogFormFields).props()).toEqual({
                blog: undefined,
                categories,
                initialFiles: undefined,
                errors: {},
            });
            expect(
                wrapper.getComponent(StoreRichTextField).props('label'),
            ).toBe(t('store.blogPosts.fields.description'));
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.blogPosts.create.submit'),
            );
            const cancel = wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'));
            expect(cancel?.props('href')).toEqual(blogsIndex());
            wrapper.unmount();
        },
    );

    it('forwards server errors and renders the processing label', async () => {
        formSlotPropsMock.mockReturnValue({
            errors: { slug: 'Slug taken', description: 'Bad content' },
            processing: true,
        });
        const wrapper = mountPage();
        await flushPromises();
        expect(
            wrapper.getComponent(StoreBlogFormFields).props('errors'),
        ).toEqual({ slug: 'Slug taken', description: 'Bad content' });
        expect(wrapper.text()).toContain('Slug taken');
        expect(wrapper.get('#description-error').text()).toBe('Bad content');
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.blogPosts.create.submitting'),
        );
        wrapper.unmount();
    });
});
