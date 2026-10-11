import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreStateFormFields from './StoreStateFormFields.vue';

describe('StoreStateFormFields', () => {
    it('mirrors validation and selects the existing country', () => {
        const wrapper = mount(StoreStateFormFields, {
            props: {
                countries: [
                    {
                        id: 2,
                        code: 'NL',
                        name: 'Netherlands',
                        zone_id: 0,
                        active: true,
                    },
                ],
                state: { id: 3, name: 'North', country_id: 2, active: true },
                errors: {},
            },
        });

        expect(wrapper.get('#name').attributes('maxlength')).toBe('255');
        expect(
            (wrapper.get('#country_id').element as HTMLSelectElement).value,
        ).toBe('2');
        expect(wrapper.get('#country_id').attributes('required')).toBeDefined();
    });
});
