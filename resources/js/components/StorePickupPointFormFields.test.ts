import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StorePickupPointFormFields from './StorePickupPointFormFields.vue';

describe('StorePickupPointFormFields', () => {
    it('renders translated fields and mirrors database limits', () => {
        const wrapper = mount(StorePickupPointFormFields, {
            props: {
                staffOptions: [{ id: 5, name: 'Manager' }],
                pickupPoint: {
                    id: 2,
                    staff_id: 5,
                    name: 'Depot',
                    address: 'Street',
                    phone: '123',
                    active: true,
                    manager: { id: 5, name: 'Manager' },
                },
                name: 'مستودع',
                address: 'شارع',
                errors: {},
            },
        });

        expect((wrapper.get('#name').element as HTMLInputElement).value).toBe(
            'مستودع',
        );
        expect(wrapper.get('#name').attributes('maxlength')).toBe('50');
        expect(wrapper.get('#phone').attributes('maxlength')).toBe('15');
        expect(
            (wrapper.get('#staff_id').element as HTMLSelectElement).value,
        ).toBe('5');
    });
});
