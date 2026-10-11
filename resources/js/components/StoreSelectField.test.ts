import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import StoreSelectField from './StoreSelectField.vue';
describe('StoreSelectField', () => {
    it('labels a required option list and selects the saved relation', () => {
        const wrapper = mount(StoreSelectField, {
            props: {
                id: 'country_id',
                label: 'Country',
                options: [
                    { id: 1, name: 'Netherlands' },
                    { id: 2, name: 'Belgium' },
                ],
                value: 2,
                required: true,
                error: 'Invalid country',
            },
        });
        expect(wrapper.get('label').attributes('for')).toBe('country_id');
        expect(wrapper.get('select').attributes()).toMatchObject({
            id: 'country_id',
            name: 'country_id',
            required: '',
        });
        expect(wrapper.get('select').element).toHaveProperty('value', '2');
        expect(
            wrapper.findAll('option').map((option) => option.text()),
        ).toEqual([t('store.reference.select'), 'Netherlands', 'Belgium']);
        expect(wrapper.text()).toContain('Invalid country');
    });
    it('allows an empty optional filter and renders labels as text', () => {
        const wrapper = mount(StoreSelectField, {
            props: {
                id: 'filter',
                label: 'Filter',
                options: [{ id: 1, name: '<img src=x onerror=alert(1)>' }],
            },
        });
        expect(wrapper.get('select').attributes('required')).toBeUndefined();
        expect(wrapper.get('select').element).toHaveProperty('value', '');
        expect(wrapper.find('img').exists()).toBe(false);
    });
});
