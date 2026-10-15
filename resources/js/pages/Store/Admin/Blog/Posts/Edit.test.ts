import { Form, Head, Link } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as blogsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogFormFields from '@/components/StoreBlogFormFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeBlog,
    storeBlogCategory,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Page from './Edit.vue';

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
const blog = storeBlog({
    description:
        '<h2 dir="rtl">وصفة</h2><p style="text-align: center;"><strong>Rice</strong></p>',
});
const categories = [
    storeBlogCategory(),
    storeBlogCategory({ id: 9, category_name: 'وصفات' }),
];
const initialFiles = {
    banner: [storeUpload({ id: 5, name: 'Banner' })],
    meta_img: [storeUpload({ id: 6, name: 'Meta' })],
};

function mountPage() {
    return mount(Page, { props: { blog, categories, initialFiles } });
}

describe('Store blog post Edit', () => {
    beforeEach(() => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/blog/12/edit',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['edit_blog', 'view_uploads'],
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
        'full mounts the stored post in the store form shell in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            await flushPromises();
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.blogPosts.edit.title'),
                action: update(blog),
                submitLabel: t('store.blogPosts.edit.submit'),
                submittingLabel: t('store.blogPosts.edit.submitting'),
                cancelHref: blogsIndex(),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.blogPosts.edit.title'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.blogPosts.edit.title'),
            );
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                update(blog),
            );
            expect(update(blog)).toEqual({
                url: '/temp/admin/blog/12',
                method: 'put',
            });
            expect(wrapper.getComponent(StoreBlogFormFields).props()).toEqual({
                blog,
                categories,
                initialFiles,
                errors: {},
            });
            expect(
                wrapper
                    .findAllComponents(StoreUploadField)
                    .map((field) => field.props('initialFiles')),
            ).toEqual([initialFiles.banner, initialFiles.meta_img]);
            expect(
                wrapper.getComponent(StoreRichTextField).props(),
            ).toMatchObject({
                name: 'description',
                modelValue: blog.description,
            });
            expect(wrapper.get('.ProseMirror h2').text()).toBe('وصفة');
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.blogPosts.edit.submit'),
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
            errors: { banner: 'Bad banner' },
            processing: true,
        });
        const wrapper = mountPage();
        await flushPromises();
        expect(
            wrapper.getComponent(StoreBlogFormFields).props('errors'),
        ).toEqual({ banner: 'Bad banner' });
        expect(wrapper.text()).toContain('Bad banner');
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.blogPosts.edit.submitting'),
        );
        wrapper.unmount();
    });
});
