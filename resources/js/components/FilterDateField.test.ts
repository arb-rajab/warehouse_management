import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FilterDateField from './FilterDateField.vue';

describe('FilterDateField', () => {
    it('renders a labelled date input bound to modelValue', () => {
        const wrapper = mount(FilterDateField, {
            props: {
                id: 'filter-date-from',
                label: 'From',
                modelValue: '2026-08-01',
            },
        });

        const label = wrapper.get('label');
        expect(label.text()).toBe('From');
        expect(label.attributes('for')).toBe('filter-date-from');

        const input = wrapper.get('input');
        expect(input.attributes('id')).toBe('filter-date-from');
        expect(input.attributes('type')).toBe('date');
        expect((input.element as HTMLInputElement).value).toBe('2026-08-01');
        expect(input.attributes('disabled')).toBeUndefined();
    });

    it('emits update:modelValue when the input changes', async () => {
        const wrapper = mount(FilterDateField, {
            props: {
                id: 'filter-date-from',
                label: 'From',
                modelValue: '',
            },
        });

        await wrapper.get('input').setValue('2026-08-01');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([
            '2026-08-01',
        ]);
    });

    it('disables the input when disabled is true', () => {
        const wrapper = mount(FilterDateField, {
            props: {
                id: 'filter-date-from',
                label: 'From',
                modelValue: '',
                disabled: true,
            },
        });

        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
    });
});
