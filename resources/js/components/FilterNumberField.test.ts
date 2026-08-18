import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FilterNumberField from './FilterNumberField.vue';

describe('FilterNumberField', () => {
    it('renders a labelled number input bound to modelValue', () => {
        const wrapper = mount(FilterNumberField, {
            props: {
                id: 'filter-within-days',
                label: 'Within days',
                placeholder: 'e.g. 7',
                modelValue: '5',
            },
        });

        const label = wrapper.get('label');
        expect(label.text()).toBe('Within days');
        expect(label.attributes('for')).toBe('filter-within-days');

        const input = wrapper.get('input');
        expect(input.attributes('id')).toBe('filter-within-days');
        expect(input.attributes('type')).toBe('number');
        expect(input.attributes('min')).toBe('1');
        expect(input.attributes('step')).toBe('1');
        expect(input.attributes('placeholder')).toBe('e.g. 7');
        expect((input.element as HTMLInputElement).value).toBe('5');
        expect(input.attributes('disabled')).toBeUndefined();
    });

    it('emits update:modelValue when the input changes', async () => {
        const wrapper = mount(FilterNumberField, {
            props: {
                id: 'filter-within-days',
                label: 'Within days',
                modelValue: '',
            },
        });

        await wrapper.get('input').setValue('3');

        // Vue auto-casts v-model on a statically type="number" input to a
        // number, same as the pre-extraction inline inputs this replaces.
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([3]);
    });

    it('disables the input when disabled is true', () => {
        const wrapper = mount(FilterNumberField, {
            props: {
                id: 'filter-within-days',
                label: 'Within days',
                modelValue: '',
                disabled: true,
            },
        });

        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
    });
});
