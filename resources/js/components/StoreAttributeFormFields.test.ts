import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import Fields from './StoreAttributeFormFields.vue';

describe('Store Attribute form fields', () => {
    it('renders every label and mirrors the backend constraints', () => {
        const wrapper = mount(Fields, { props: { errors: {} } });
        expect(wrapper.get('#name').attributes('required')).toBeDefined();
        expect(wrapper.get('#name').attributes('maxlength')).toBe('50');
        expect(wrapper.get('label[for="name"]').text()).toBe(
            t('store.attributes.fields.name') + '*' + t('common.required'),
        );
    });
    it('passes initial values and server errors to the shared fields', () => {
        const wrapper = mount(Fields, {
            props: { name: 'Initial name', errors: { name: 'Invalid name.' } },
        });
        expect(wrapper.findAllComponents(FormField)[0].props()).toMatchObject({
            id: 'name',
            value: 'Initial name',
            error: 'Invalid name.',
        });
    });
    it('retains typed input when the server returns a validation error', async () => {
        const wrapper = mount(Fields, { props: { errors: {} } });
        const input = wrapper.get('#name');
        await input.setValue('Typed content');
        await wrapper.setProps({ errors: { name: 'Invalid.' } });
        expect((input.element as HTMLInputElement).value).toBe('Typed content');
    });
});
