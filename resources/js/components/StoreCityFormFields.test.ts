import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreCityFormFields from './StoreCityFormFields.vue';

describe('StoreCityFormFields', () => {
    it('renders the translated name and numeric cost constraints', () => {
        const wrapper = mount(StoreCityFormFields, {
            props: {
                states: [{ id: 2, name: 'North', country_id: 1, active: true }],
                city: {
                    id: 3,
                    name: 'Utrecht',
                    state_id: 2,
                    cost: '2.50',
                    active: true,
                },
                name: 'أوترخت',
                errors: {},
            },
        });

        expect((wrapper.get('#name').element as HTMLInputElement).value).toBe(
            'أوترخت',
        );
        expect(wrapper.get('#cost').attributes()).toMatchObject({
            min: '0',
            step: '0.01',
            required: '',
        });
    });
});
