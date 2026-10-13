import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { storeState } from '@/testing/factories';
import StoreStateFormFields from './StoreStateFormFields.vue';
function mountFields(errors: Record<string, string> = {}) {
    return mount(StoreStateFormFields, {
        props: {
            state: storeState(),
            errors,
            countries: [{ id: 11, name: 'Netherlands' }],
        },
    });
}
describe('StoreStateFormFields', () => {
    it('fills the edited name and associates every required field with a label', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeState().name,
        );

        for (const id of ['name', 'country_id']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(wrapper.get(`label[for=${id}]`).text()).not.toBe('');
        }

        for (const key of ['reference.name', 'reference.country']) {
            expect(wrapper.text()).toContain(t(`store.${key}`));
        }
    });
    it('shows server field errors without replacing the edited value', () => {
        const wrapper = mountFields(
            Object.fromEntries(
                ['name', 'country_id'].map((id) => [id, `Invalid ${id}`]),
            ),
        );

        for (const id of ['name', 'country_id']) {
            expect(wrapper.text()).toContain(`Invalid ${id}`);
        }

        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeState().name,
        );
    });
    it('mirrors database limits in native constraints', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').attributes('maxlength')).toBe('255');
        expect(wrapper.get('#country_id').element).toHaveProperty(
            'value',
            '11',
        );
    });
});
