import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import WarehouseFormFields from './WarehouseFormFields.vue';

describe('WarehouseFormFields', () => {
    it('renders its labels', () => {
        const wrapper = mount(WarehouseFormFields, { props: { errors: {} } });

        expect(wrapper.text()).toContain(t('warehouses.fields.name'));
        expect(wrapper.text()).toContain(t('warehouses.fields.code'));
        expect(wrapper.text()).toContain(t('warehouses.fields.address'));
    });

    it('marks only the name as required, mirroring the backend rules', () => {
        const wrapper = mount(WarehouseFormFields, { props: { errors: {} } });

        const input = (id: string) =>
            wrapper.get(`#${id}`).element as HTMLInputElement;

        expect(input('name').required).toBe(true);
        expect(input('code').required).toBe(false);
        expect(input('address').required).toBe(false);
        // The asterisk follows the label of the required field only.
        expect(wrapper.get('label[for="name"]').text()).toContain('*');
        expect(wrapper.get('label[for="code"]').text()).not.toContain('*');
        expect(wrapper.get('label[for="address"]').text()).not.toContain('*');
    });

    it('caps each field at its backend maximum length', () => {
        const wrapper = mount(WarehouseFormFields, { props: { errors: {} } });

        expect(wrapper.get('#name').attributes('maxlength')).toBe('100');
        expect(wrapper.get('#code').attributes('maxlength')).toBe('20');
        expect(wrapper.get('#address').attributes('maxlength')).toBe('255');
    });

    it('fills the fields from the values it is given, an absent code or address as blank', () => {
        const wrapper = mount(WarehouseFormFields, {
            props: {
                name: 'Main',
                code: null,
                address: '1 Dock Road',
                errors: {},
            },
        });

        const input = (id: string) =>
            wrapper.get(`#${id}`).element as HTMLInputElement;

        expect(input('name').value).toBe('Main');
        expect(input('code').value).toBe('');
        expect(input('address').value).toBe('1 Dock Road');
    });

    it('shows validation error messages', () => {
        const wrapper = mount(WarehouseFormFields, {
            props: {
                errors: {
                    name: 'The name has already been taken.',
                    code: 'The code has already been taken.',
                    address:
                        'The address field must not be greater than 255 characters.',
                },
            },
        });

        expect(wrapper.text()).toContain('The name has already been taken.');
        expect(wrapper.text()).toContain('The code has already been taken.');
        expect(wrapper.text()).toContain(
            'The address field must not be greater than 255 characters.',
        );
    });

    it('retains values the user typed when the errors prop changes after a failed submit', async () => {
        const wrapper = mount(WarehouseFormFields, { props: { errors: {} } });

        await wrapper.get('#name').setValue('Main');
        await wrapper.get('#code').setValue('MN');

        await wrapper.setProps({
            errors: { name: 'The name has already been taken.' },
        });

        expect((wrapper.get('#name').element as HTMLInputElement).value).toBe(
            'Main',
        );
        expect((wrapper.get('#code').element as HTMLInputElement).value).toBe(
            'MN',
        );
    });

    it('prefixes its ids when idPrefix is given, so it can render more than once on the same page', () => {
        const wrapper = mount(WarehouseFormFields, {
            props: { errors: {}, idPrefix: 'preview-' },
        });

        expect(wrapper.find('#preview-name').exists()).toBe(true);
        expect(wrapper.find('#preview-code').exists()).toBe(true);
        expect(wrapper.find('#preview-address').exists()).toBe(true);
        expect(wrapper.find('#name').exists()).toBe(false);
    });
});
