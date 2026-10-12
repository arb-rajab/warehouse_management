import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { storePickupPoint } from '@/testing/factories';
import StorePickupPointFormFields from './StorePickupPointFormFields.vue';
function mountFields(errors: Record<string, string> = {}) {
    return mount(StorePickupPointFormFields, {
        props: {
            pickupPoint: storePickupPoint(),
            errors,
            staffOptions: [{ id: 16, name: 'Manager' }],
        },
    });
}
describe('StorePickupPointFormFields', () => {
    it('fills the edited name and associates every required field with a label', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storePickupPoint().name,
        );

        for (const id of [
            'name',
            'address',
            'phone',
            'staff_id',
            'pick_up_status',
        ]) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(wrapper.get(`label[for=${id}]`).text()).not.toBe('');
        }

        for (const key of [
            'reference.name',
            'pickupPoints.address',
            'pickupPoints.phone',
            'pickupPoints.manager',
            'reference.status',
        ]) {
            expect(wrapper.text()).toContain(t(`store.${key}`));
        }
    });
    it('shows server field errors without replacing the edited value', () => {
        const wrapper = mountFields(
            Object.fromEntries(
                ['name', 'address', 'phone', 'staff_id', 'pick_up_status'].map(
                    (id) => [id, `Invalid ${id}`],
                ),
            ),
        );

        for (const id of [
            'name',
            'address',
            'phone',
            'staff_id',
            'pick_up_status',
        ]) {
            expect(wrapper.text()).toContain(`Invalid ${id}`);
        }

        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storePickupPoint().name,
        );
    });
    it('mirrors database limits in native constraints', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').attributes('maxlength')).toBe('50');
        expect(wrapper.get('#phone').attributes()).toMatchObject({
            type: 'tel',
            maxlength: '15',
        });
        expect(wrapper.get('#address').attributes('maxlength')).toBe('65535');
        expect(wrapper.get('#staff_id').element).toHaveProperty('value', '16');
        expect(wrapper.get('#pick_up_status').element).toHaveProperty(
            'value',
            '1',
        );
    });
});
