import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as pagesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsitePageFormFields from '@/components/StoreWebsitePageFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { paginated, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Create from './Create.vue';

const { usePageMock, useHttpMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
    formSlotPropsMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        useHttp: useHttpMock,
        router: { post: vi.fn() },
    };
});

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/website/custom-pages/create',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['add_website_page'] }),
        }),
    });
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, paginated([])),
    );
});
afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('Website page Create', () => {
    it.each(['en', 'ar'] as const)(
        'uses the store form page with its labels and action in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(Create, {
                props: { homeUrl: 'http://localhost/temp' },
            });
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.website.pages.createTitle'),
                submitLabel: t('store.website.pages.create'),
                submittingLabel: t('store.website.saving'),
                cancelHref: pagesIndex(),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.getComponent(Form).props('action')).toEqual(store());
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.website.pages.create'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.website.pages.createTitle'),
            );
            wrapper.unmount();
        },
    );

    it('passes the fields the address prefix and the form errors', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { slug: 'The slug has already been taken.' },
            processing: false,
        });
        const wrapper = mount(Create, {
            props: { homeUrl: 'http://localhost/temp' },
        });
        expect(
            wrapper.getComponent(StoreWebsitePageFormFields).props(),
        ).toEqual({
            page: undefined,
            title: undefined,
            content: undefined,
            homeUrl: 'http://localhost/temp',
            initialFiles: undefined,
            errors: { slug: 'The slug has already been taken.' },
        });
        expect(wrapper.text()).toContain('The slug has already been taken.');
        wrapper.unmount();
    });
});
