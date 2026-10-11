import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import Fields from './StoreAttributeValueFormFields.vue';

describe('Store AttributeValue form fields', () => {
    it('renders every label and mirrors the backend constraints', () => {
        const wrapper = mount(Fields, {
            props: { attributeId: 7, errors: {} },
        });
        expect(wrapper.get('#value').attributes('required')).toBeDefined();
        expect(wrapper.get('#value').attributes('maxlength')).toBe('255');
        expect(wrapper.get('label[for="value"]').text()).toBe(
            t('store.attributeValues.fields.value'),
        );
        expect(
            wrapper.get('input[name=attribute_id]').attributes('value'),
        ).toBe('7');
    });
    it('passes initial values and server errors to the shared fields', () => {
        const wrapper = mount(Fields, {
            props: {
                attributeId: 7,
                value: 'Initial value',
                errors: {
                    value: 'Invalid value.',
                    attribute_id: 'Unknown attribute.',
                },
            },
        });
        expect(wrapper.findAllComponents(FormField)[0].props()).toMatchObject({
            id: 'value',
            value: 'Initial value',
            error: 'Invalid value.',
        });
        expect(wrapper.text()).toContain('Unknown attribute.');
    });
    it('retains typed input when the server returns a validation error', async () => {
        const wrapper = mount(Fields, {
            props: { attributeId: 7, errors: {} },
        });
        const input = wrapper.get('#value');
        await input.setValue('Typed content');
        await wrapper.setProps({ errors: { value: 'Invalid.' } });
        expect((input.element as HTMLInputElement).value).toBe('Typed content');
    });
});
