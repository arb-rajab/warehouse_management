import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreTaxFormFields from './StoreTaxFormFields.vue';

describe('StoreTaxFormFields', () => {
    it('renders the tax name with native constraints', () => {
        const wrapper = mount(StoreTaxFormFields, {
            props: { tax: { id: 1, name: 'VAT', active: true }, errors: {} },
        });

        expect((wrapper.get('#name').element as HTMLInputElement).value).toBe(
            'VAT',
        );
        expect(wrapper.get('#name').attributes()).toMatchObject({
            maxlength: '255',
            required: '',
        });
    });
});
