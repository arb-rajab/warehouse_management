import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import StoreSellerFormFields from './StoreSellerFormFields.vue';

describe('seller account fields', () => {
    it('renders every label and mirrors required type and length rules', () => {
        const wrapper = mount(StoreSellerFormFields, {
            props: {
                name: 'Sam',
                email: 'sam@example.com',
                errors: {},
                passwordRequired: true,
            },
        });
        expect(wrapper.findAllComponents(FormField)).toHaveLength(3);

        for (const field of ['name', 'email', 'password']) {
            expect(wrapper.get(`label[for="${field}"]`).text()).toBe(
                t(`store.sellers.${field}`),
            );
            expect(wrapper.get(`#${field}`).attributes()).toMatchObject({
                required: '',
                maxlength: '255',
            });
        }

        expect(wrapper.get('#email').attributes('type')).toBe('email');
        expect(wrapper.get('#password').attributes('type')).toBe('password');
        expect(wrapper.get('#name').attributes('value')).toBe('Sam');
        expect(wrapper.get('#email').attributes('value')).toBe(
            'sam@example.com',
        );
    });
    it('keeps edit passwords optional with a hint and displays server errors', () => {
        const wrapper = mount(StoreSellerFormFields, {
            props: { errors: { email: 'Taken' } },
        });
        expect(wrapper.get('#password').attributes('required')).toBeUndefined();
        expect(wrapper.get('#password').attributes('placeholder')).toBe(
            t('store.sellers.passwordHint'),
        );
        expect(wrapper.text()).toContain('Taken');
    });
});
