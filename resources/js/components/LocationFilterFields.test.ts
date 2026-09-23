import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LocationFilterFields from './LocationFilterFields.vue';

const rows = [
    { id: 1, letter: 'A' },
    { id: 2, letter: 'B' },
];

function mountFields(rowIds: string[] = [], columnNumber = '') {
    return mount(LocationFilterFields, {
        props: {
            idPrefix: 'filter',
            rows,
            maxColumnNumber: 3,
            rowIds,
            columnNumber,
        },
    });
}

describe('LocationFilterFields', () => {
    it('renders a row multi-select button and a column select, prefixed by idPrefix', () => {
        const wrapper = mountFields();

        expect(wrapper.get('button').attributes('id')).toBe('filter-row');
        expect(wrapper.get('select').attributes('id')).toBe('filter-column');

        const labels = wrapper.findAll('label');
        expect(labels[0].text()).toBe('Row');
        expect(labels[0].attributes('for')).toBe('filter-row');
        expect(labels[1].text()).toBe('Column');
        expect(labels[1].attributes('for')).toBe('filter-column');
    });

    it('renders the row options from the rows prop, and column options from 1..maxColumnNumber', async () => {
        const wrapper = mountFields();

        await wrapper.get('button').trigger('click');

        const rowOptions = wrapper
            .get('[role="listbox"]')
            .findAll('[role="option"]');
        expect(rowOptions.map((option) => option.text())).toEqual(['A', 'B']);

        const columnSelect = wrapper.get('select');
        expect(
            columnSelect.findAll('option').map((option) => option.text()),
        ).toEqual(['All', '1', '2', '3']);
    });

    it('uses a distinct DOM id per idPrefix so the fields can render twice on one page', () => {
        const wrapper = mount(LocationFilterFields, {
            props: {
                idPrefix: 'column-filter',
                rows,
                maxColumnNumber: 3,
                rowIds: [],
                columnNumber: '',
            },
        });

        expect(wrapper.get('button').attributes('id')).toBe(
            'column-filter-row',
        );
        expect(wrapper.get('select').attributes('id')).toBe(
            'column-filter-column',
        );
    });

    it('emits update:rowIds with the added row when a row checkbox is checked', async () => {
        const wrapper = mountFields();

        await wrapper.get('button').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);

        expect(wrapper.emitted('update:rowIds')?.[0]).toEqual([['2']]);
    });

    it('supports selecting multiple rows at once', async () => {
        const wrapper = mountFields(['1']);

        await wrapper.get('button').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);

        expect(wrapper.emitted('update:rowIds')?.[0]).toEqual([['1', '2']]);
    });

    it('emits update:columnNumber when the column select changes', async () => {
        const wrapper = mountFields();

        await wrapper.get('select').setValue('2');

        // The column select's options bind numeric values (1..maxColumnNumber),
        // so the emitted value is a number too (same convention as
        // FilterNumberField, see js.md).
        expect(wrapper.emitted('update:columnNumber')?.[0]).toEqual([2]);
    });

    it('checks the checkboxes matching rowIds and selects the option matching columnNumber', async () => {
        const wrapper = mountFields(['2'], '3');

        await wrapper.get('button').trigger('click');

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(
            checkboxes.map(
                (checkbox) => (checkbox.element as HTMLInputElement).checked,
            ),
        ).toEqual([false, true]);

        const columnSelect = wrapper.get('select');
        expect((columnSelect.element as HTMLSelectElement).value).toBe('3');
    });
});
