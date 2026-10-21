import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { t } from '@/lib/i18n';
import { storeRole } from '@/testing/factories';
import StoreStaffFormFields from './StoreStaffFormFields.vue';

const roles = [
    storeRole({ id: 3, name: 'Clerk' }),
    storeRole({ id: 5, name: 'Manager' }),
];

function mountFields(
    props: Record<string, unknown> = {},
    options: { attachTo?: HTMLElement } = {},
) {
    return mount(StoreStaffFormFields, {
        props: { roles, errors: {}, ...props },
        ...options,
    });
}

function field(wrapper: ReturnType<typeof mountFields>, id: string) {
    return wrapper
        .findAllComponents(FormField)
        .find((formField) => formField.props('id') === id);
}

describe('StoreStaffFormFields', () => {
    it('renders every label', () => {
        const wrapper = mountFields();

        for (const key of [
            'name',
            'email',
            'phone',
            'role',
            'newPassword',
            'confirmPassword',
        ]) {
            expect(wrapper.text()).toContain(t(`store.staff.fields.${key}`));
        }

        expect(t('store.staff.fields.phone')).toBe('Phone');
        expect(t('store.staff.fields.role')).toBe('Role');
    });

    it('labels the password "Password" and requires it when creating', () => {
        const wrapper = mountFields({ passwordRequired: true });

        expect(field(wrapper, 'password')?.props('label')).toBe(
            t('store.staff.fields.password'),
        );
        expect(wrapper.get('#password').attributes('required')).toBeDefined();
        expect(
            wrapper.get('#password_confirmation').attributes('required'),
        ).toBeDefined();
    });

    it('leaves the password optional when editing, with a hint', () => {
        const wrapper = mountFields();

        expect(wrapper.get('#password').attributes('required')).toBeUndefined();
        expect(wrapper.get('#password').attributes('placeholder')).toBe(
            t('store.staff.fields.passwordPlaceholder'),
        );
    });

    it("mirrors the request's rules as native constraints", () => {
        const wrapper = mountFields();

        for (const id of ['name', 'email', 'mobile']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('255');
        }

        expect(wrapper.get('#email').attributes('type')).toBe('email');
        expect(wrapper.get('#role_id').attributes('required')).toBeDefined();
        expect(wrapper.get('#password').attributes('maxlength')).toBe('255');
    });

    it('fills in the staff member being edited', () => {
        const wrapper = mountFields({
            name: 'Sam Staff',
            email: 'sam@example.com',
            phone: '0612345678',
            roleId: 5,
        });

        expect(field(wrapper, 'name')?.props('value')).toBe('Sam Staff');
        expect(field(wrapper, 'email')?.props('value')).toBe('sam@example.com');
        expect(field(wrapper, 'mobile')?.props('value')).toBe('0612345678');
        expect(
            (wrapper.get('#role_id').element as HTMLSelectElement).value,
        ).toBe('5');
    });

    it('offers every role, and asks for one when none is chosen', () => {
        const wrapper = mountFields();
        const options = wrapper.findAll('#role_id option');

        expect(options.map((option) => option.text())).toEqual([
            t('store.staff.fields.rolePlaceholder'),
            'Clerk',
            'Manager',
        ]);
        expect(options.map((option) => option.attributes('value'))).toEqual([
            '',
            '3',
            '5',
        ]);
        expect(
            (wrapper.get('#role_id').element as HTMLSelectElement).value,
        ).toBe('');
    });

    it('shows each field error, the role one included', () => {
        const wrapper = mountFields({
            errors: {
                name: 'The name field is required.',
                email: 'The email has already been taken.',
                mobile: 'The mobile field is required.',
                role_id: 'The selected role id is invalid.',
                password: 'The password field must be at least 8 characters.',
            },
        });

        expect(field(wrapper, 'name')?.props('error')).toBe(
            'The name field is required.',
        );
        expect(field(wrapper, 'email')?.props('error')).toBe(
            'The email has already been taken.',
        );
        expect(field(wrapper, 'mobile')?.props('error')).toBe(
            'The mobile field is required.',
        );
        expect(field(wrapper, 'password')?.props('error')).toBe(
            'The password field must be at least 8 characters.',
        );
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((error) => error.props('message')),
        ).toContain('The selected role id is invalid.');
    });

    it('flags a confirmation that does not match the password', async () => {
        // The check finds both inputs by id, so they must be in the document.
        const wrapper = mountFields(
            { passwordRequired: true },
            { attachTo: document.body },
        );

        await wrapper.get('#password').setValue('Password123!');
        await wrapper.get('#password_confirmation').setValue('Different123!');

        expect(field(wrapper, 'password_confirmation')?.props('error')).toBe(
            t('users.fields.passwordMismatch'),
        );

        wrapper.unmount();
    });
});

describe('StoreStaffFormFields required marks', () => {
    function markedIds(wrapper: ReturnType<typeof mountFields>): string[] {
        return wrapper
            .findAll('label')
            .filter((label) => label.findComponent(RequiredMark).exists())
            .map((label) => label.attributes('for') ?? '');
    }

    function requiredIds(wrapper: ReturnType<typeof mountFields>): string[] {
        return wrapper
            .findAll('input, select')
            .filter((control) => control.attributes('required') !== undefined)
            .map((control) => control.attributes('id') ?? '');
    }

    it('marks every field StoreStaffRequest requires, the role included', () => {
        const wrapper = mountFields({ passwordRequired: true });

        expect(markedIds(wrapper)).toEqual([
            'name',
            'email',
            'mobile',
            'role_id',
            'password',
            'password_confirmation',
        ]);
        expect(wrapper.get('label[for="role_id"]').text()).toBe(
            t('store.staff.fields.role') + '*' + t('common.required'),
        );
    });

    it('leaves the optional password of UpdateStaffRequest unmarked', () => {
        const wrapper = mountFields();

        expect(markedIds(wrapper)).toEqual([
            'name',
            'email',
            'mobile',
            'role_id',
        ]);
        expect(wrapper.get('label[for="password"]').text()).toBe(
            t('store.staff.fields.newPassword'),
        );
    });

    it.each([true, false])(
        'marks exactly the controls it requires (password required: %s)',
        (passwordRequired) => {
            const wrapper = mountFields({ passwordRequired });

            expect(markedIds(wrapper)).toEqual(requiredIds(wrapper));
        },
    );
});
