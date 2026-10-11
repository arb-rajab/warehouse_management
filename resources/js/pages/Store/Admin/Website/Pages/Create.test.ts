import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive, nextTick } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';

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
import Page from './Create.vue';
describe('CMS Create', () => {
    const props = () => ({
        lang: 'en',
        languages: [
            { code: 'en', name: 'English' },
            { code: 'ar', name: 'Arabic' },
        ],
    });
    it('full-mounts the store form and submits its exact page contract', async () => {
        const wrapper = mount(Page, { props: props() });
        await nextTick();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual(store());
        await wrapper.get('#title').setValue('Edited');
        const values = (
            form.props('transform') as (
                data: unknown,
            ) => Record<string, unknown>
        )({ type: 'home_page' });
        expect(values.title).toBe('Edited');
        expect(values.lang).toBe('en');
        expect(values).not.toHaveProperty('type');
        expect(values).not.toHaveProperty('id');
        expect(
            wrapper.getComponent(ResourceFormPage).props('cancelHref'),
        ).toEqual({ url: '/temp/admin/website/pages', method: 'get' });
        wrapper.unmount();
    });
});
