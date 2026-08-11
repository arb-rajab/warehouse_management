import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DataTable from './DataTable.vue';

const columns = ['Letter', 'Cells', 'Flats'];

type TestRow = { id: number; letter: string };

function mountTable(rows: TestRow[]) {
    return mount(DataTable<TestRow>, {
        props: { columns, rows, emptyMessage: 'No rows yet.' },
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
    });
});
