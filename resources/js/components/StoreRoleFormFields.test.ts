import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { t } from '@/lib/i18n';
import { permissionGroup } from '@/testing/factories';
import StoreRoleFormFields from './StoreRoleFormFields.vue';

const groups = [
    permissionGroup(),
    permissionGroup({
        section: 'order',
        label: 'Order',
        permissions: [{ id: 7, label: 'View All Orders' }],
    }),
];

function mountFields(props: Record<string, unknown> = {}) {
    return mount(StoreRoleFormFields, {
        props: { permissionGroups: groups, errors: {}, ...props },
    });
}

describe('StoreRoleFormFields', () => {
    it('renders its labels', () => {
        const wrapper = mountFields();

        expect(wrapper.getComponent(FormField).props('label')).toBe(
            t('store.roles.fields.name'),
        );
        expect(wrapper.get('h2').text()).toBe(
            t('store.roles.fields.permissions'),
        );
        expect(t('store.roles.fields.permissions')).toBe('Permissions');
    });

    it("mirrors the request's name rule as native constraints", () => {
        const wrapper = mountFields();

        expect(wrapper.get('#name').attributes('required')).toBeDefined();
        expect(wrapper.get('#name').attributes('maxlength')).toBe('50');
    });

    it('groups a checkbox per permission under its section', () => {
        const wrapper = mountFields();
        const fieldsets = wrapper.findAll('fieldset');

        expect(
            fieldsets.map((fieldset) => fieldset.get('legend').text()),
        ).toEqual(['Staff', 'Order']);
        expect(fieldsets[0].text()).toContain('View All Staffs');
        expect(fieldsets[0].text()).toContain('Add Staff');
        expect(
            fieldsets[1]
                .findAll('input[type="checkbox"]')
                .map((input) => [
                    input.attributes('name'),
                    input.attributes('value'),
                ]),
        ).toEqual([['permissions[]', '7']]);
    });

    it("checks the role's own permissions only", () => {
        const wrapper = mountFields({ checkedPermissionIds: [2, 7] });

        expect(
            wrapper
                .findAll('input[type="checkbox"]')
                .map((input) => (input.element as HTMLInputElement).checked),
        ).toEqual([false, true, true]);
    });

    it('fills in the name being edited', () => {
        const wrapper = mountFields({ name: 'Clerk' });

        expect(wrapper.getComponent(FormField).props('value')).toBe('Clerk');
    });

    it('shows the name error and the first permission error', () => {
        const wrapper = mountFields({
            errors: {
                name: 'The name has already been taken.',
                'permissions.1': 'The selected permissions.1 is invalid.',
            },
        });

        expect(wrapper.getComponent(FormField).props('error')).toBe(
            'The name has already been taken.',
        );
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((error) => error.props('message')),
        ).toContain('The selected permissions.1 is invalid.');
    });

    it('keeps a checkbox the user ticked when errors come back', async () => {
        const wrapper = mountFields();
        const checkbox = wrapper.get('input[type="checkbox"]');

        await checkbox.setValue(true);
        await wrapper.setProps({ errors: { name: 'Required.' } });

        expect((checkbox.element as HTMLInputElement).checked).toBe(true);
    });
});

describe('StoreRoleFormFields required marks', () => {
    it('marks the required name and none of the optional permissions', () => {
        const wrapper = mount(StoreRoleFormFields, {
            props: {
                permissionGroups: groups,
                errors: {},
            },
        });
        const marked = wrapper
            .findAll('label')
            .filter((label) => label.findComponent(RequiredMark).exists());

        expect(marked.map((label) => label.attributes('for'))).toEqual([
            'name',
        ]);
        expect(
            wrapper.get('input[name="permissions[]"]').attributes('required'),
        ).toBeUndefined();
    });
});
