import { Warehouse } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LogoutLink from '@/components/LogoutLink.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import ChangePassword from './ChangePassword.vue';

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
        Link: createLinkStub(),
        Form: createFormStub(formSlotPropsMock),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function mountPage() {
    usePageMock.mockReturnValue({ props: { locale: 'en' } });

    return mount(ChangePassword);
}

describe('ChangePassword', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders the brand title with a brand icon', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('auth.changePassword.brand'));
        expect(wrapper.findComponent(Warehouse).exists()).toBe(true);
    });

    it('renders the title and description explaining why the change is required', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('auth.changePassword.title'));
        expect(wrapper.text()).toContain(t('auth.changePassword.description'));
    });

    it('renders the current password, new password, and confirmation field labels', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('auth.changePassword.currentPassword'),
        );
        expect(wrapper.text()).toContain(t('auth.changePassword.password'));
        expect(wrapper.text()).toContain(
            t('auth.changePassword.confirmPassword'),
        );
    });

    it('submits the form to the password change route', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/password/change',
        );
    });

    it('renders the current password field with the right input type and autocomplete hint', () => {
        const wrapper = mountPage();

        const currentPassword = wrapper.get('#current_password');
        expect(currentPassword.attributes('type')).toBe('password');
        expect(currentPassword.attributes('autocomplete')).toBe(
            'current-password',
        );
    });

    it('renders the password and confirmation fields with the right input types and autocomplete hints', () => {
        const wrapper = mountPage();

        const password = wrapper.get('#password');
        expect(password.attributes('type')).toBe('password');
        expect(password.attributes('autocomplete')).toBe('new-password');

        const confirmation = wrapper.get('#password_confirmation');
        expect(confirmation.attributes('type')).toBe('password');
        expect(confirmation.attributes('autocomplete')).toBe('new-password');
    });

    it('marks the current password, password, and confirmation fields as required with a max length, mirroring the backend rule', () => {
        const wrapper = mountPage();

        const currentPassword = wrapper.get('#current_password')
            .element as HTMLInputElement;
        expect(currentPassword.required).toBe(true);
        expect(currentPassword.maxLength).toBe(255);

        const password = wrapper.get('#password').element as HTMLInputElement;
        expect(password.required).toBe(true);
        expect(password.maxLength).toBe(255);

        const confirmation = wrapper.get('#password_confirmation')
            .element as HTMLInputElement;
        expect(confirmation.required).toBe(true);
        expect(confirmation.maxLength).toBe(255);
    });

    it('flags the confirmation field when it does not match the password', async () => {
        usePageMock.mockReturnValue({ props: { locale: 'en' } });
        const wrapper = mount(ChangePassword, { attachTo: document.body });

        await wrapper.get('#password').setValue('Password123!');
        await wrapper.get('#password_confirmation').setValue('Different123!');

        expect(wrapper.text()).toContain(t('users.fields.passwordMismatch'));

        await wrapper.get('#password_confirmation').setValue('Password123!');

        expect(wrapper.text()).not.toContain(
            t('users.fields.passwordMismatch'),
        );

        wrapper.unmount();
    });

    it('shows a validation error message for the password field', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { password: 'The password field is required.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.text()).toContain('The password field is required.');
    });

    it('shows a validation error message for the current password field', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { current_password: 'The password is incorrect.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.text()).toContain('The password is incorrect.');
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('auth.changePassword.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(
            t('auth.changePassword.submit'),
        );
    });

    it('renders the language switcher', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('English');
        expect(wrapper.text()).toContain('Arabic');
    });

    it('renders a logout link', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(LogoutLink).exists()).toBe(true);
        expect(wrapper.text()).toContain(t('nav.logout'));
    });
});
