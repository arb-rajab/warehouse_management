import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FilterCheckbox from './FilterCheckbox.vue';

describe('FilterCheckbox', () => {
    it('renders a checkbox and label with the given id and label', () => {
        const wrapper = mount(FilterCheckbox, {
            props: {
                id: 'filter-expired',
                label: 'Expired',
                modelValue: false,
            },
        });

        const checkbox = wrapper.get('input[type="checkbox"]');
        expect(checkbox.attributes('id')).toBe('filter-expired');

        const label = wrapper.get('label');
        expect(label.text()).toBe('Expired');
        expect(label.attributes('for')).toBe('filter-expired');
    });

    it('reflects the modelValue as checked/unchecked', () => {
        const checked = mount(FilterCheckbox, {
            props: { id: 'filter-expired', label: 'Expired', modelValue: true },
        });
        expect((checked.get('input').element as HTMLInputElement).checked).toBe(
            true,
        );

        const unchecked = mount(FilterCheckbox, {
            props: {
                id: 'filter-expired',
                label: 'Expired',
                modelValue: false,
            },
        });
        expect(
            (unchecked.get('input').element as HTMLInputElement).checked,
        ).toBe(false);
    });

    it('emits update:modelValue when toggled', async () => {
        const wrapper = mount(FilterCheckbox, {
            props: {
                id: 'filter-expired',
                label: 'Expired',
                modelValue: false,
            },
        });

        await wrapper.get('input').setValue(true);

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true]);
    });
});
