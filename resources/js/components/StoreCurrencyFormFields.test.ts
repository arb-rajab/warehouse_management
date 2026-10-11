import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreCurrencyFormFields from './StoreCurrencyFormFields.vue';

describe('StoreCurrencyFormFields', () => {
    it('fills every field and mirrors request limits', () => {
        const wrapper = mount(StoreCurrencyFormFields, {
            props: {
                currency: {
                    id: 1,
                    name: 'Euro',
                    symbol: '€',
                    code: 'EUR',
                    exchange_rate: '1.00000',
                    active: true,
                },
                errors: {},
            },
        });

        expect((wrapper.get('#code').element as HTMLInputElement).value).toBe(
            'EUR',
        );
        expect(wrapper.get('#code').attributes('maxlength')).toBe('20');
        expect(wrapper.get('#exchange_rate').attributes()).toMatchObject({
            min: '0',
            step: '0.00001',
            required: '',
        });
    });
});
