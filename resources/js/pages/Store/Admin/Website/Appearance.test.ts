import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive, nextTick } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteAppearanceController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteScriptsForm from '@/components/StoreWebsiteScriptsForm.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t, i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { storeWebsiteAppearanceSettings } from '@/testing/factories';
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
import Page from './Appearance.vue';
describe('Website Appearance', () => {
    const props = () => ({
        settings: storeWebsiteAppearanceSettings(),
        scripts: null,
    });
    it('uses the store shell and exact settings endpoint and transform', async () => {
        const wrapper = mount(Page, { props: props() });
        await nextTick();
        expect(wrapper.findAllComponents(StoreAdminLayout)).toHaveLength(1);
        expect(wrapper.getComponent(ResourceFormPage).props('title')).toBe(
            t('store.website.appearance.title'),
        );
        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual(update());
        const transform = form.props('transform') as (data: unknown) => unknown;
        expect(transform({ forged: 'must-not-submit' })).toEqual({
            settings: props().settings,
        });
        wrapper.unmount();
    });
    it('shows a separate authorized script form only when its narrow scripts prop is supplied', async () => {
        const wrapper = mount(Page, {
            props: {
                ...props(),
                scripts: {
                    header_script: '<script>kept</script>',
                    footer_script: '',
                },
            },
        });
        await nextTick();
        expect(
            wrapper.getComponent(StoreWebsiteScriptsForm).props('settings'),
        ).toEqual({
            header_script: '<script>kept</script>',
            footer_script: '',
        });
        expect(wrapper.findAll('form')).toHaveLength(2);
        expect(wrapper.find('script').exists()).toBe(false);
        wrapper.unmount();
    });
});
