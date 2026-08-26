import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { rowCells } from './dom';

function mountTable(rows: string[][]) {
    const Table = defineComponent({
        render() {
            return h('table', [
                h(
                    'tbody',
                    rows.map((cells) =>
                        h(
                            'tr',
                            cells.map((text) => h('td', text)),
                        ),
                    ),
                ),
            ]);
        },
    });

    return mount(Table);
}

describe('rowCells', () => {
    it('defaults to the first row’s cells', () => {
        const wrapper = mountTable([
            ['a1', 'a2'],
            ['b1', 'b2'],
        ]);

        const cells = rowCells(wrapper);

        expect(cells.map((cell) => cell.text())).toEqual(['a1', 'a2']);
    });

    it('returns the cells of the row at the given index, not other rows', () => {
        const wrapper = mountTable([
            ['a1', 'a2'],
            ['b1', 'b2'],
            ['c1', 'c2'],
        ]);

        const cells = rowCells(wrapper, 1);

        expect(cells.map((cell) => cell.text())).toEqual(['b1', 'b2']);
    });
});
