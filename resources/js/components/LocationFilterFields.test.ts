import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LocationFilterFields from './LocationFilterFields.vue';

const rows = [
    { id: 1, letter: 'A' },
    { id: 2, letter: 'B' },
];

describe('LocationFilterFields', () => {
    it('renders a row select and a column select, prefixed by idPrefix', () => {
        const wrapper = mount(LocationFilterFields, {
            props: {
                idPrefix: 'filter',
                rows,
                maxColumnNumber: 3,
                rowId: '',
                columnNumber: '',
            },
        });

        const selects = wrapper.findAll('select');
        expect(selects).toHaveLength(2);
        expect(selects[0].attributes('id')).toBe('filter-row');
        expect(selects[1].attributes('id')).toBe('filter-column');

        const labels = wrapper.findAll('label');
        expect(labels[0].text()).toBe('Row');
        expect(labels[0].attributes('for')).toBe('filter-row');
        expect(labels[1].text()).toBe('Column');
        expect(labels[1].attributes('for')).toBe('filter-column');
    });

    it('renders the row options from the rows prop, and column options from 1..maxColumnNumber', () => {
        const wrapper = mount(LocationFilterFields, {
            props: {
                idPrefix: 'filter',
                rows,
                maxColumnNumber: 3,
                rowId: '',
                columnNumber: '',
            },
        });

        const [rowSelect, columnSelect] = wrapper.findAll('select');

        expect(
            rowSelect.findAll('option').map((option) => option.text()),
        ).toEqual(['All', 'A', 'B']);

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
                rowId: '',
                columnNumber: '',
            },
        });

        const selects = wrapper.findAll('select');
        expect(selects[0].attributes('id')).toBe('column-filter-row');
        expect(selects[1].attributes('id')).toBe('column-filter-column');
    });

    it('emits update:rowId when the row select changes', async () => {
        const wrapper = mount(LocationFilterFields, {
            props: {
                idPrefix: 'filter',
                rows,
                maxColumnNumber: 3,
                rowId: '',
                columnNumber: '',
            },
        });

        await wrapper.findAll('select')[0].setValue('2');

        // The row select's options bind numeric `row.id` values, so Vue's
        // native-select v-model preserves the actual type instead of the
        // declared `string` model (same convention as FilterNumberField,
        // see js.md).
        expect(wrapper.emitted('update:rowId')?.[0]).toEqual([2]);
    });

    it('emits update:columnNumber when the column select changes', async () => {
        const wrapper = mount(LocationFilterFields, {
            props: {
                idPrefix: 'filter',
                rows,
                maxColumnNumber: 3,
                rowId: '',
                columnNumber: '',
            },
        });

        await wrapper.findAll('select')[1].setValue('2');

        // The column select's options bind numeric values (1..maxColumnNumber),
        // so the emitted value is a number too — see the note above.
        expect(wrapper.emitted('update:columnNumber')?.[0]).toEqual([2]);
    });

    it('selects the options matching rowId and columnNumber', () => {
        const wrapper = mount(LocationFilterFields, {
            props: {
                idPrefix: 'filter',
                rows,
                maxColumnNumber: 3,
                rowId: '2',
                columnNumber: '3',
            },
        });

        const [rowSelect, columnSelect] = wrapper.findAll('select');
        expect((rowSelect.element as HTMLSelectElement).value).toBe('2');
        expect((columnSelect.element as HTMLSelectElement).value).toBe('3');
    });
});
