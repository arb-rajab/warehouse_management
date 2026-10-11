import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive, nextTick } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';

import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t, i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { storeWebsiteFooterSettings } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
const { usePageMock, routerGetMock, slotMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    slotMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(slotMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
        useHttp: () =>
            reactive({
                processing: false,
                get: () => Promise.resolve(),
                post: () => Promise.resolve(),
            }),
    };
});
beforeEach(() => {
    vi.clearAllMocks();
    slotMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/website',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: [
                    'footer_setup',
                    'website_appearance',
                    'manage_website_scripts',
                    'edit_website_page',
                    'reply_to_support_tickets',
                ],
            }),
        }),
    });
    i18n.global.locale.value = 'en';
});
import Page from './Footer.vue';
describe('Website Footer', () => {
    const props = () => ({
        settings: storeWebsiteFooterSettings(),
        lang: 'en',
        languages: [
            { code: 'en', name: 'English' },
            { code: 'ar', name: 'Arabic' },
        ],
    });
    it('uses the store shell and exact settings endpoint and transform', async () => {
        const wrapper = mount(Page, { props: props() });
        await nextTick();
        expect(wrapper.findAllComponents(StoreAdminLayout)).toHaveLength(1);
        expect(wrapper.getComponent(ResourceFormPage).props('title')).toBe(
            t('store.website.footer.title'),
        );
        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual(update());
        const transform = form.props('transform') as (data: unknown) => unknown;
        expect(transform({ forged: 'must-not-submit' })).toEqual({
            settings: props().settings,
            lang: 'en',
        });
        wrapper.unmount();
    });
    it('changes the selected language through its Wayfinder endpoint', async () => {
        const wrapper = mount(Page, { props: props() });
        await wrapper.get('select').setValue('ar');
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/website/footer?lang=ar',
            {},
            { preserveState: false },
        );
        wrapper.unmount();
    });
});
