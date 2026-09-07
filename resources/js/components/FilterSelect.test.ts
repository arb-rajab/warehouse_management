import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { plainFieldInputClass } from '@/lib/filters';
import FilterSelect from './FilterSelect.vue';

describe('FilterSelect', () => {
    it('renders a labelled select with an "all" option followed by the given options', () => {
        const wrapper = mount(FilterSelect, {
            props: {
                id: 'filter-row',
                label: 'Row',
                allLabel: 'All',
                modelValue: '',
                options: [
                    { value: 1, label: 'A' },
                    { value: 2, label: 'B' },
                ],
            },
        });

        const label = wrapper.get('label');
        expect(label.text()).toBe('Row');
        expect(label.attributes('for')).toBe('filter-row');

        const select = wrapper.get('select');
        expect(select.attributes('id')).toBe('filter-row');
        expect(select.attributes('class')).toBe(plainFieldInputClass);

        const options = wrapper.findAll('option');
        expect(options.map((option) => option.text())).toEqual([
            'All',
            'A',
            'B',
        ]);
        expect(options[0].attributes('value')).toBe('');
    });

    it('selects the option matching modelValue', () => {
        const wrapper = mount(FilterSelect, {
            props: {
                id: 'filter-row',
                label: 'Row',
                allLabel: 'All',
                modelValue: '2',
                options: [
                    { value: 1, label: 'A' },
                    { value: 2, label: 'B' },
                ],
            },
        });

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            '2',
        );
    });

    it('emits update:modelValue when a different option is selected', async () => {
        const wrapper = mount(FilterSelect, {
            props: {
                id: 'filter-row',
                label: 'Row',
                allLabel: 'All',
                modelValue: '',
                options: [
                    { value: 1, label: 'A' },
                    { value: 2, label: 'B' },
                ],
            },
        });

        await wrapper.get('select').setValue('2');

        // The select's option values are bound dynamically, so Vue's v-model
        // resolves to the option's actual value (a number here), not the
        // stringified DOM attribute — matching the pre-extraction behavior.
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([2]);
    });
});
