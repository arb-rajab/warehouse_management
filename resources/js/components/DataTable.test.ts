import { ArrowUp, ArrowUpDown, Filter, PackageSearch } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';
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

    it('shows a filter icon on any column with a filterKey, whether or not it is currently filtered', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }], {
            columns: [
                { label: 'Cells', sortKey: 'cells_count', filterKey: 'cells' },
                { label: 'Letter', filterKey: 'letter' },
            ],
        });

        const headers = wrapper.findAll('thead th');
        expect(headers[0].findComponent(Filter).exists()).toBe(true);
        expect(headers[1].findComponent(Filter).exists()).toBe(true);
    });

    it('does not show a filter icon on a column with no filterKey, even if marked filtered', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }], {
            columns: [
                { label: 'Cells', sortKey: 'cells_count', filtered: true },
            ],
        });

        expect(wrapper.get('thead th').findComponent(Filter).exists()).toBe(
            false,
        );
    });

    it('colors the filter icon to reflect whether the column is currently filtered', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }], {
            columns: [
                { label: 'Cells', filterKey: 'cells', filtered: true },
                { label: 'Flats', filterKey: 'flats', filtered: false },
            ],
        });

        const headers = wrapper.findAll('thead th');
        expect(headers[0].find('button[title]').classes()).toContain(
            'text-blue-600',
        );
        expect(headers[1].find('button[title]').classes()).not.toContain(
            'text-blue-600',
        );
    });

    it('hides the filter icon on a filterIconAlwaysVisible:false column until it is actually filtered', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }], {
            columns: [
                {
                    label: 'Cells',
                    filterKey: 'cells',
                    filterIconAlwaysVisible: false,
                    filtered: false,
                },
            ],
        });

        expect(wrapper.get('thead th').findComponent(Filter).exists()).toBe(
            false,
        );
    });

    it('reveals the filterIconAlwaysVisible:false column icon once it is filtered', () => {
        const wrapper = mountTable([{ id: 1, letter: 'A' }], {
            columns: [
                {
                    label: 'Cells',
                    filterKey: 'cells',
                    filterIconAlwaysVisible: false,
                    filtered: true,
                },
            ],
        });

        const button = wrapper.get('thead th').get('button[title]');
        expect(button.findComponent(Filter).exists()).toBe(true);
        expect(button.classes()).toContain('text-blue-600');
    });

    function mountTableWithFilterSlot(
        columns: {
            label: string;
            sortKey?: string;
            filtered?: boolean;
            filterKey?: string;
        }[],
        openFilterKey: ReturnType<typeof ref<string | null>>,
    ) {
        return mount(DataTable<TestRow>, {
            props: {
                columns,
                rows: [{ id: 1, letter: 'A' }],
                emptyMessage: 'No rows yet.',
                openFilterKey: openFilterKey.value,
                'onUpdate:openFilterKey': (value: string | null) => {
                    openFilterKey.value = value;
                },
            },
            slots: {
                row: `<td>{{ params.row.letter }}</td>`,
                'column-filter': `<div class="filter-slot">filters for {{ params.filterKey }}</div>`,
            },
        });
    }

    it('opens the matching column-filter popover when its icon is clicked', async () => {
        const openFilterKey = ref<string | null>(null);
        const wrapper = mountTableWithFilterSlot(
            [{ label: 'Cells', filterKey: 'cells' }],
            openFilterKey,
        );

        expect(wrapper.find('.filter-slot').exists()).toBe(false);

        await wrapper.get('button[title]').trigger('click');
        await wrapper.setProps({ openFilterKey: openFilterKey.value });

        expect(wrapper.find('.filter-slot').text()).toBe('filters for cells');
    });

    it('renders the popover outside the overflow-hidden table wrapper, so a short table cannot clip it', async () => {
        const openFilterKey = ref<string | null>(null);
        const wrapper = mountTableWithFilterSlot(
            [{ label: 'Cells', filterKey: 'cells' }],
            openFilterKey,
        );

        await wrapper.get('button[title]').trigger('click');
        await wrapper.setProps({ openFilterKey: openFilterKey.value });

        const clippedAncestor = wrapper
            .get('.filter-slot')
            .element.closest('.overflow-hidden');
        expect(clippedAncestor).toBeNull();
    });

    it('closes the popover when its icon is clicked again', async () => {
        const openFilterKey = ref<string | null>(null);
        const wrapper = mountTableWithFilterSlot(
            [{ label: 'Cells', filterKey: 'cells' }],
            openFilterKey,
        );

        await wrapper.get('button[title]').trigger('click');
        await wrapper.setProps({ openFilterKey: openFilterKey.value });
        expect(wrapper.find('.filter-slot').exists()).toBe(true);

        await wrapper.get('button[title]').trigger('click');
        await wrapper.setProps({ openFilterKey: openFilterKey.value });
        expect(wrapper.find('.filter-slot').exists()).toBe(false);
    });

    it("switches to a different column's popover when its icon is clicked while another is open", async () => {
        const openFilterKey = ref<string | null>(null);
        const wrapper = mountTableWithFilterSlot(
            [
                { label: 'Cells', filterKey: 'cells' },
                { label: 'Flats', filterKey: 'flats' },
            ],
            openFilterKey,
        );

        const buttons = wrapper.findAll('button[title]');
        await buttons[0].trigger('click');
        await wrapper.setProps({ openFilterKey: openFilterKey.value });
        expect(wrapper.find('.filter-slot').text()).toBe('filters for cells');

        await buttons[1].trigger('click');
        await wrapper.setProps({ openFilterKey: openFilterKey.value });
        expect(wrapper.find('.filter-slot').text()).toBe('filters for flats');
    });

    it('closes the popover when Escape is pressed', async () => {
        const openFilterKey = ref<string | null>(null);
        const wrapper = mountTableWithFilterSlot(
            [{ label: 'Cells', filterKey: 'cells' }],
            openFilterKey,
        );

        await wrapper.get('button[title]').trigger('click');
        await wrapper.setProps({ openFilterKey: openFilterKey.value });
        expect(wrapper.find('.filter-slot').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.setProps({ openFilterKey: openFilterKey.value });

        expect(wrapper.find('.filter-slot').exists()).toBe(false);
    });

    it('closes the popover when a click happens outside it', async () => {
        const openFilterKeyModel = ref<string | null>(null);
        const Host = defineComponent({
            setup() {
                return () =>
                    h('div', [
                        h(DataTable<TestRow>, {
                            columns: [{ label: 'Cells', filterKey: 'cells' }],
                            rows: [{ id: 1, letter: 'A' }],
                            emptyMessage: 'No rows yet.',
                            openFilterKey: openFilterKeyModel.value,
                            'onUpdate:openFilterKey': (
                                value: string | null,
                            ) => {
                                openFilterKeyModel.value = value;
                            },
                        }),
                        h('span', { id: 'outside' }, 'outside'),
                    ]);
            },
        });
        const wrapper = mount(Host, { attachTo: document.body });

        await wrapper.get('button[title]').trigger('click');
        expect(openFilterKeyModel.value).toBe('cells');

        document
            .getElementById('outside')
            ?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(openFilterKeyModel.value).toBe(null);

        wrapper.unmount();
    });
});
