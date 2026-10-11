import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { storeTax } from '@/testing/factories';
import StoreTaxFormFields from './StoreTaxFormFields.vue';
function mountFields(errors: Record<string, string> = {}) {
    return mount(StoreTaxFormFields, { props: { tax: storeTax(), errors } });
}
describe('StoreTaxFormFields', () => {
    it('fills the edited name and associates every required field with a label', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeTax().name,
        );

        for (const id of ['name']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(wrapper.get(`label[for=${id}]`).text()).not.toBe('');
        }

        for (const key of ['reference.name']) {
            expect(wrapper.text()).toContain(t(`store.${key}`));
        }
    });
    it('shows server field errors without replacing the edited value', () => {
        const wrapper = mountFields(
            Object.fromEntries(['name'].map((id) => [id, `Invalid ${id}`])),
        );

        for (const id of ['name']) {
            expect(wrapper.text()).toContain(`Invalid ${id}`);
        }

        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeTax().name,
        );
    });
    it('mirrors database limits in native constraints', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').attributes('maxlength')).toBe('255');
    });
});
