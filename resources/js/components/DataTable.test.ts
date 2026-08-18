import { ArrowUp, ArrowUpDown, PackageSearch } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DataTable from './DataTable.vue';

const columns = ['Letter', 'Cells', 'Flats'];

type TestRow = { id: number; letter: string };

function mountTable(rows: TestRow[], extraProps: Record<string, unknown> = {}) {
    return mount(DataTable<TestRow>, {
        props: { columns, rows, emptyMessage: 'No rows yet.', ...extraProps },
        slots: {
            row: `<td class="letter-cell">{{ params.row.letter }}</td>`,
        },
    });
}

describe('DataTable', () => {
    it('renders a header cell for every column', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }]);

        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
            columns,
        );
    });

    it('renders one body row per row and exposes the row to the slot', () => {
        const wrapper = mountTable([
            { id: 1, letter: 'A' },
            { id: 2, letter: 'B' },
        ]);

        const cells = wrapper.findAll('.letter-cell');
        expect(cells).toHaveLength(2);
        expect(cells.map((cell) => cell.text())).toEqual(['A', 'B']);
    });

    it('does not render the empty state while there are rows', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }]);

        expect(wrapper.text()).not.toContain('No rows yet.');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    });

    it('renders the empty state spanning every column when there are no rows', () => {
        const wrapper = mountTable([]);

        expect(wrapper.findAll('.letter-cell')).toHaveLength(0);

        const emptyCell = wrapper.get('tbody td');
        expect(emptyCell.text()).toBe('No rows yet.');
        expect(emptyCell.attributes('colspan')).toBe(String(columns.length));
        expect(emptyCell.findComponent(PackageSearch).exists()).toBe(true);
    });

    it('renders a plain column header as text, with no sort button', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }]);

        const headers = wrapper.findAll('thead th');
        expect(headers[0].find('button').exists()).toBe(false);
        expect(headers[0].text()).toBe('Letter');
    });

    it('renders a sortable column header as a button and emits its sort key when clicked', async () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }], {
            columns: [
                'Letter',
                { label: 'Cells', sortKey: 'cells_count' },
                'Flats',
            ],
        });

        const headers = wrapper.findAll('thead th');
        const button = headers[1].get('button');
        expect(button.text()).toContain('Cells');

        await button.trigger('click');

        expect(wrapper.emitted('sort')).toEqual([['cells_count']]);
    });

    it('shows an up arrow on the active ascending sort column and a down arrow on the active descending column', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }], {
            columns: [
                { label: 'Cells', sortKey: 'cells_count' },
                { label: 'Flats', sortKey: 'flats_count' },
            ],
            sort: { by: 'cells_count', direction: 'asc' },
        });

        const headers = wrapper.findAll('thead th');
        expect(headers[0].findComponent(ArrowUp).exists()).toBe(true);
        expect(headers[1].findComponent(ArrowUpDown).exists()).toBe(true);
    });
});
