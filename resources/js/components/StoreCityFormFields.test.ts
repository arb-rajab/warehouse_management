import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { storeCity } from '@/testing/factories';
import StoreCityFormFields from './StoreCityFormFields.vue';
function mountFields(errors: Record<string, string> = {}) {
    return mount(StoreCityFormFields, {
        props: {
            city: storeCity(),
            errors,
            states: [{ id: 12, name: 'North Holland' }],
        },
    });
}
describe('StoreCityFormFields', () => {
    it('fills the edited name and associates every required field with a label', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeCity().name,
        );

        for (const id of ['name', 'state_id', 'cost']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(wrapper.get(`label[for=${id}]`).text()).not.toBe('');
        }

        for (const key of [
            'reference.name',
            'reference.state',
            'cities.cost',
        ]) {
            expect(wrapper.text()).toContain(t(`store.${key}`));
        }
    });
    it('shows server field errors without replacing the edited value', () => {
        const wrapper = mountFields(
            Object.fromEntries(
                ['name', 'state_id', 'cost'].map((id) => [id, `Invalid ${id}`]),
            ),
        );

        for (const id of ['name', 'state_id', 'cost']) {
            expect(wrapper.text()).toContain(`Invalid ${id}`);
        }

        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeCity().name,
        );
    });
    it('mirrors database limits in native constraints', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').attributes('maxlength')).toBe('255');
        expect(wrapper.get('#cost').attributes()).toMatchObject({
            type: 'number',
            min: '0',
            step: '0.01',
        });
        expect(wrapper.get('#state_id').element).toHaveProperty('value', '12');
    });
});
