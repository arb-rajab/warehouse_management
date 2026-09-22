import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SettingFormFields from '@/components/SettingFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { setting } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

const { formSlotPropsMock, usePageMock, routerPostMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function mountPage(
    overrides: Partial<{ qr_code_width: number; qr_code_height: number }> = {},
) {
    usePageMock.mockReturnValue({
        url: '/admin/settings',
        props: defaultAuthProps(),
    });

    return mount(Edit, { props: { setting: setting(overrides) } });
}

describe('Settings Edit', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it('renders the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('settings.edit.title'));
    });

    it('submits the form to the settings update route', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/admin/settings',
        );
    });

    it('passes the current QR code size to the form fields', () => {
        const wrapper = mountPage({
            qr_code_width: 300,
            qr_code_height: 350,
        });

        expect(wrapper.findComponent(SettingFormFields).props()).toMatchObject({
            qrCodeWidth: 300,
            qrCodeHeight: 350,
        });
    });

    it('forwards validation errors to the form fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { qr_code_width: 'The qr code width field is required.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(
            wrapper.findComponent(SettingFormFields).props('errors'),
        ).toEqual({
            qr_code_width: 'The qr code width field is required.',
        });
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('settings.edit.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(
            t('settings.edit.submit'),
        );
    });

    it('renders a help link to the settings help topic', () => {
        const wrapper = mountPage();

        const helpLink = wrapper
            .findAll('a')
            .find((a) => a.attributes('href') === '/admin/help/settings');
        expect(helpLink).toBeTruthy();
    });
});
