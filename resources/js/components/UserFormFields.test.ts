import { CircleAlert, TriangleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import UserFormFields from './UserFormFields.vue';

describe('UserFormFields', () => {
    it('renders its labels', () => {
        const wrapper = mount(UserFormFields, { props: { errors: {} } });

        expect(wrapper.text()).toContain('Name');
        expect(wrapper.text()).toContain('Email');
        expect(wrapper.text()).toContain('New password');
        expect(wrapper.text()).toContain('Confirm password');
        expect(wrapper.text()).toContain('Admin access');
    });

    it('shows "Password" instead of "New password" when passwordRequired is true', () => {
        const wrapper = mount(UserFormFields, {
            props: { errors: {}, passwordRequired: true },
        });

        expect(wrapper.text()).toContain('Password');
        expect(wrapper.text()).not.toContain('New password');
    });

    it('shows validation error messages', () => {
        const wrapper = mount(UserFormFields, {
            props: {
                errors: {
                    name: 'The name field is required.',
                    email: 'The email has already been taken.',
                    is_admin: 'The is admin field is invalid.',
                },
            },
        });

        expect(wrapper.text()).toContain('The name field is required.');
        expect(wrapper.text()).toContain('The email has already been taken.');
        expect(wrapper.text()).toContain('The is admin field is invalid.');
        expect(wrapper.findComponent(CircleAlert).exists()).toBe(true);
    });

    it('retains values the user typed and toggled when the errors prop changes after a failed submit', async () => {
        const wrapper = mount(UserFormFields, { props: { errors: {} } });

        await wrapper.find('#name').setValue('Jane Doe');
        await wrapper.find('#email').setValue('jane@example.com');
        await wrapper.find('input[type="checkbox"]').setValue(true);

        await wrapper.setProps({
            errors: { email: 'The email has already been taken.' },
        });

        expect((wrapper.find('#name').element as HTMLInputElement).value).toBe(
            'Jane Doe',
        );
        expect((wrapper.find('#email').element as HTMLInputElement).value).toBe(
            'jane@example.com',
        );
        expect(
            (wrapper.find('input[type="checkbox"]').element as HTMLInputElement)
                .checked,
        ).toBe(true);
    });

    it('disables the admin toggle when disableAdminToggle is true', () => {
        const wrapper = mount(UserFormFields, {
            props: { errors: {}, disableAdminToggle: true },
        });

        expect(wrapper.text()).toContain(
            'You cannot remove your own admin access.',
        );
        expect(wrapper.findComponent(TriangleAlert).exists()).toBe(true);
    });

    it('marks the name and email fields as required with a max length, mirroring the backend rules', () => {
        const wrapper = mount(UserFormFields, { props: { errors: {} } });

        const name = wrapper.get('#name').element as HTMLInputElement;
        expect(name.required).toBe(true);
        expect(name.maxLength).toBe(255);

        const email = wrapper.get('#email').element as HTMLInputElement;
        expect(email.required).toBe(true);
        expect(email.maxLength).toBe(255);
    });

    it('requires the password and its confirmation only when passwordRequired is true', () => {
        const optional = mount(UserFormFields, { props: { errors: {} } });
        expect(
            (optional.get('#password').element as HTMLInputElement).required,
        ).toBe(false);
        expect(
            (optional.get('#password_confirmation').element as HTMLInputElement)
                .required,
        ).toBe(false);

        const required = mount(UserFormFields, {
            props: { errors: {}, passwordRequired: true },
        });
        expect(
            (required.get('#password').element as HTMLInputElement).required,
        ).toBe(true);
        expect(
            (required.get('#password_confirmation').element as HTMLInputElement)
                .required,
        ).toBe(true);
    });

    it('flags the confirmation field as invalid when it does not match the password', async () => {
        const wrapper = mount(UserFormFields, {
            props: { errors: {} },
            attachTo: document.body,
        });

        const password = wrapper.get('#password').element as HTMLInputElement;
        const confirmation = wrapper.get('#password_confirmation')
            .element as HTMLInputElement;

        await wrapper.get('#password').setValue('Password123!');
        await wrapper.get('#password_confirmation').setValue('Different123!');

        expect(confirmation.validationMessage).not.toBe('');

        await wrapper.get('#password_confirmation').setValue('Password123!');

        expect(confirmation.validationMessage).toBe('');
        expect(password.validationMessage).toBe('');

        wrapper.unmount();
    });

    it('shows an app-styled mismatch message alongside the native validation error', async () => {
        const wrapper = mount(UserFormFields, {
            props: { errors: {} },
            attachTo: document.body,
        });

        await wrapper.get('#password').setValue('Password123!');
        await wrapper.get('#password_confirmation').setValue('Different123!');

        expect(wrapper.text()).toContain('Passwords do not match.');

        await wrapper.get('#password_confirmation').setValue('Password123!');

        expect(wrapper.text()).not.toContain('Passwords do not match.');

        wrapper.unmount();
    });
});
