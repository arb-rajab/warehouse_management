import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteScriptsController';
import SubmitButton from '@/components/SubmitButton.vue';
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
import StoreWebsiteScriptsForm from './StoreWebsiteScriptsForm.vue';
describe('separate script form', () => {
    it('escapes script code as textarea content and posts only two allowlisted keys', async () => {
        const wrapper = mount(StoreWebsiteScriptsForm, {
            props: {
                settings: {
                    header_script: '<script>alert(1)</script>',
                    footer_script: '',
                },
            },
        });
        expect(wrapper.find('script').exists()).toBe(false);
        await wrapper.get('#footer_script').setValue('<script>footer</script>');
        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual(update());
        expect(
            (form.props('transform') as (input: unknown) => unknown)({
                website_name: 'forged',
            }),
        ).toEqual({
            settings: {
                header_script: '<script>alert(1)</script>',
                footer_script: '<script>footer</script>',
            },
        });
        expect(wrapper.get('#header_script').attributes('maxlength')).toBe(
            '100000',
        );
    });
    it('enforces its UI permission gate and shows errors/processing', async () => {
        usePageMock.mockReturnValue({
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['website_appearance'],
                }),
            }),
        });
        const refused = mount(StoreWebsiteScriptsForm, {
            props: { settings: { header_script: '', footer_script: '' } },
        });
        expect(refused.find('form').exists()).toBe(false);
        slotMock.mockReturnValue({
            errors: { 'settings.header_script': 'Too long' },
            processing: true,
        });
        usePageMock.mockReturnValue({
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['manage_website_scripts'],
                }),
            }),
        });
        const allowed = mount(StoreWebsiteScriptsForm, {
            props: { settings: { header_script: '', footer_script: '' } },
        });
        expect(allowed.text()).toContain('Too long');
        expect(allowed.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });
});
