import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import DataTable from '@/components/DataTable.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import { t } from '@/lib/i18n';
import { useTableColumns } from '@/lib/useTableColumns';
import {
    expectColumnCanBeHidden,
    expectHeadersMatchCells,
    headerTexts,
    storeColumns,
    storedColumns,
} from '@/testing/tableColumns';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ usePage: usePageMock }));

/** A minimal list page: two data columns and the fixed Actions column. */
function listPage(broken = false) {
    return mount(
        defineComponent({
            setup() {
                const columns = useTableColumns({
                    listId: 'test.helper',
                    columns: [
                        {
                            key: 'name',
                            label: 'store.customers.fields.name',
                            required: true,
                        },
                        { key: 'email', label: 'store.customers.fields.email' },
                    ],
                });
                const { shown, visibleColumns } = columns;

                return () => [
                    h(StoreColumnPicker, { state: columns }),
                    h(
                        DataTable,
                        {
                            columns: [
                                ...visibleColumns.value.map((c) => t(c.label)),
                                'Actions',
                            ],
                            rows: [{ id: 1 }],
                            emptyMessage: 'none',
                        },
                        {
                            row: () => [
                                h('td', 'Amy'),
                                // The broken page forgets the visibility check.
                                broken || shown.value.email
                                    ? h('td', 'a@b.c')
                                    : null,
                                h('td', 'Edit'),
                            ],
                        },
                    ),
                ];
            },
        }),
    );
}

beforeEach(() => {
    window.localStorage.clear();
    usePageMock.mockReturnValue({ props: { auth: { user: { id: 7 } } } });
});

it('hides a column from the header and the cells and remembers it', async () => {
    const wrapper = listPage();
    expectHeadersMatchCells(wrapper);
    await expectColumnCanBeHidden(wrapper, 'test.helper', {
        key: 'email',
        label: t('store.customers.fields.email'),
    });
    wrapper.unmount();
});

it('reads the header texts and a stored choice', () => {
    storeColumns('test.helper', ['name']);
    expect(storedColumns('test.helper')).toEqual(['name']);
    expect(storedColumns('test.unknown')).toBeNull();
    const wrapper = listPage();
    expect(headerTexts(wrapper)).toEqual([
        t('store.customers.fields.name'),
        'Actions',
    ]);
    wrapper.unmount();
});

it('notices a cell that is still rendered after its header went', () => {
    storeColumns('test.helper', ['name']);
    const wrapper = listPage(true);
    expect(() => expectHeadersMatchCells(wrapper)).toThrow();
    wrapper.unmount();
});
