import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    index as pagesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreWebsitePageFormFields from '@/components/StoreWebsitePageFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeUpload,
    storeWebsitePageForm,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Edit from './Edit.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        useHttp: useHttpMock,
        router: { post: vi.fn() },
    };
});

const page = storeWebsitePageForm({ meta_image: '31' });
const metaImage = storeUpload({ id: 31, name: 'Meta' });
const languages = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'العربية' },
];
const props = {
    page,
    title: 'من نحن',
    content: '<p>عنا</p>',
    lang: 'ar',
    languages,
    homeUrl: 'http://localhost/temp',
    initialFiles: { meta_image: [metaImage] },
};

beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        url: '/temp/admin/website/custom-pages/edit/about?lang=ar',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['edit_website_page', 'view_uploads'],
            }),
        }),
    });
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, paginated([])),
    );
});
afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('Website page Edit', () => {
    it.each(['en', 'ar'] as const)(
        'uses the store form page with its labels and the bound update action in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(Edit, { props });
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.website.pages.editTitle'),
                submitLabel: t('store.website.pages.update'),
                submittingLabel: t('store.website.saving'),
                cancelHref: pagesIndex(),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                update(page),
            );
            expect(update(page).method).toBe('put');
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.website.pages.update'),
            );
            wrapper.unmount();
        },
    );

    it('switches language through the edit route and posts the edited language', () => {
        const wrapper = mount(Edit, { props });
        const tabs = wrapper.getComponent(StoreLanguageTabs);
        expect(tabs.props('lang')).toBe('ar');
        expect(
            wrapper
                .findAllComponents(Link)
                .filter((link) => link.text() === 'English')
                .map((link) => link.props('href')),
        ).toEqual([edit(page, { query: { lang: 'en' } })]);
        expect(
            (wrapper.get('input[name="lang"]').element as HTMLInputElement)
                .value,
        ).toBe('ar');
        wrapper.unmount();
    });

    it('passes the fields the page, its language texts and the permitted preview', () => {
        const wrapper = mount(Edit, { props });
        expect(
            wrapper.getComponent(StoreWebsitePageFormFields).props(),
        ).toEqual({
            page,
            title: 'من نحن',
            content: '<p>عنا</p>',
            homeUrl: 'http://localhost/temp',
            initialFiles: { meta_image: [metaImage] },
            errors: {},
        });
        wrapper.unmount();
    });
});
