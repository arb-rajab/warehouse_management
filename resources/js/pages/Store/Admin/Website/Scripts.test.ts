import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import StoreWebsiteScriptsForm from '@/components/StoreWebsiteScriptsForm.vue';
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
import Scripts from './Scripts.vue';
it('full-mounts the independent script screen in the store shell', () => {
    const settings = {
        header_script: '<script>Stored code</script>',
        footer_script: '',
    };
    const wrapper = mount(Scripts, { props: { settings } });
    expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    expect(
        wrapper.getComponent(StoreWebsiteScriptsForm).props('settings'),
    ).toEqual(settings);
    expect(wrapper.find('script').exists()).toBe(false);
});
