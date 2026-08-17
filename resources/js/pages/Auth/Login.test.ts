import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import Login from './Login.vue';

const { formSlotPropsMock, usePageMock, routerPostMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const FormStub = defineComponent({
        props: ['action'],
        setup(props, { slots }) {
            return () =>
                h(
                    'form',
                    {
                        'data-action-url':
                            typeof props.action === 'string'
                                ? props.action
                                : props.action?.url,
                    },
                    slots.default?.(formSlotPropsMock()),
                );
        },
    });

    return {
        Head: defineComponent({ render: () => null }),
        Form: FormStub,
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

const honeypotProp = {
    enabled: true,
    nameFieldName: 'my_name_abc123',
    validFromFieldName: 'valid_from',
    encryptedValidFrom: 'encrypted-timestamp',
};

function mountPage(honeypot = honeypotProp) {
    usePageMock.mockReturnValue({ props: { locale: 'en' } });

    return mount(Login, { props: { honeypot } });
}

describe('Login', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders the brand title', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('auth.login.brand'));
    });

    it('submits the form to the login route', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/login',
        );
    });

    it('renders the email and password fields with the right input types and autocomplete hints', () => {
        const wrapper = mountPage();

        const email = wrapper.get('#email');
        expect(email.attributes('type')).toBe('email');
        expect(email.attributes('autocomplete')).toBe('username');

        const password = wrapper.get('#password');
        expect(password.attributes('type')).toBe('password');
        expect(password.attributes('autocomplete')).toBe('current-password');
    });

    it('marks the email and password fields as required with a max length, mirroring the backend rules', () => {
        const wrapper = mountPage();

        const email = wrapper.get('#email');
        expect((email.element as HTMLInputElement).required).toBe(true);
        expect(email.attributes('maxlength')).toBe('255');

        const password = wrapper.get('#password');
        expect((password.element as HTMLInputElement).required).toBe(true);
        expect(password.attributes('maxlength')).toBe('255');
    });

    it('shows validation error messages for the email and password fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                email: 'These credentials do not match our records.',
                password: 'The password field is required.',
            },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            'These credentials do not match our records.',
        );
        expect(wrapper.text()).toContain('The password field is required.');
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('auth.login.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(t('auth.login.submit'));
    });

    it('renders the language switcher', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('English');
        expect(wrapper.text()).toContain('Arabic');
    });

    it('renders the honeypot fields with the names and value provided by the backend', () => {
        const wrapper = mountPage();

        const nameField = wrapper.get(
            `input[name="${honeypotProp.nameFieldName}"]`,
        );
        expect(nameField.attributes('value')).toBe('');
        expect(nameField.attributes('autocomplete')).toBe('nope');
        expect(nameField.attributes('tabindex')).toBe('-1');

        const validFromField = wrapper.get(
            `input[name="${honeypotProp.validFromFieldName}"]`,
        );
        expect(validFromField.attributes('value')).toBe(
            honeypotProp.encryptedValidFrom,
        );
    });

    it('omits the honeypot fields when disabled', () => {
        const wrapper = mountPage({ ...honeypotProp, enabled: false });

        expect(
            wrapper
                .find(`input[name="${honeypotProp.nameFieldName}"]`)
                .exists(),
        ).toBe(false);
    });
});
