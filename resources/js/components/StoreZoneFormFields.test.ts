import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreZoneFormFields from './StoreZoneFormFields.vue';

describe('StoreZoneFormFields', () => {
    it('requires at least one country and retains checked countries', () => {
        const wrapper = mount(StoreZoneFormFields, {
            props: {
                countries: [
                    {
                        id: 2,
                        code: 'NL',
                        name: 'Netherlands',
                        zone_id: 4,
                        active: true,
                    },
                ],
                zone: { id: 4, name: 'Europe' },
                countryIds: [2],
                errors: {},
            },
        });
        const checkbox = wrapper.get('input[type="checkbox"]');

        expect((checkbox.element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.get('#name').attributes('required')).toBeDefined();
    });
});
