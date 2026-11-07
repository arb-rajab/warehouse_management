import { beforeEach, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';
import { tableColumnsStorageKey, useTableColumns } from '@/lib/useTableColumns';
import type { TableColumnDef } from '@/lib/useTableColumns';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ usePage: usePageMock }));

const columns: TableColumnDef[] = [
    { key: 'name', label: 'a.name', required: true },
    { key: 'email', label: 'a.email' },
    { key: 'phone', label: 'a.phone' },
    { key: 'notes', label: 'a.notes', defaultVisible: false },
    { key: 'secret', label: 'a.secret', available: false },
];
const key = tableColumnsStorageKey(7, 'test.list');

function signedInAs(id: number | null): void {
    usePageMock.mockReturnValue({
        props: { auth: { user: id === null ? null : { id } } },
    });
}

function visible(state: ReturnType<typeof useTableColumns>): string[] {
    return state.visibleColumns.value.map((column) => column.key);
}

beforeEach(() => {
    window.localStorage.clear();
    signedInAs(7);
});

it('keys the choice per store user and per list', () => {
    expect(tableColumnsStorageKey(7, 'sales.orders')).toBe(
        'store.tableColumns.7.sales.orders',
    );
    expect(tableColumnsStorageKey(null, 'sales.orders')).toBe(
        'store.tableColumns.0.sales.orders',
    );
});

it('starts from the required and default-visible available columns', () => {
    const state = useTableColumns({ listId: 'test.list', columns });
    expect(visible(state)).toEqual(['name', 'email', 'phone']);
    expect(state.available.value.map((column) => column.key)).toEqual([
        'name',
        'email',
        'phone',
        'notes',
    ]);
    expect(state.shown.value).toEqual({
        name: true,
        email: true,
        phone: true,
        notes: false,
    });
    // Nothing is written until the user chooses.
    expect(window.localStorage.getItem(key)).toBeNull();
});

it('remembers a choice in table order, ignores stale keys and keeps required ones', () => {
    window.localStorage.setItem(
        key,
        JSON.stringify(['notes', 'email', 'secret', 'nope']),
    );
    const state = useTableColumns({ listId: 'test.list', columns });
    expect(visible(state)).toEqual(['name', 'email', 'notes']);
    expect(state.shown.value.phone).toBe(false);
});

it('falls back to the defaults for empty, junk, foreign or blocked storage', () => {
    const defaults = ['name', 'email', 'phone'];

    for (const stored of ['[]', '{oops', '"text"', '["secret","nope"]']) {
        window.localStorage.setItem(key, stored);
        expect(
            visible(useTableColumns({ listId: 'test.list', columns })),
        ).toEqual(defaults);
    }

    const get = vi
        .spyOn(Storage.prototype, 'getItem')
        .mockImplementation(() => {
            throw new Error('blocked');
        });
    const set = vi
        .spyOn(Storage.prototype, 'setItem')
        .mockImplementation(() => {
            throw new Error('blocked');
        });
    const state = useTableColumns({ listId: 'test.list', columns });
    expect(visible(state)).toEqual(defaults);
    expect(() => state.toggle('phone', false)).not.toThrow();
    // The choice still applies for this page view.
    expect(visible(state)).toEqual(['name', 'email']);
    get.mockRestore();
    set.mockRestore();
});

it('toggles, shows all and resets, saving each choice', () => {
    const state = useTableColumns({ listId: 'test.list', columns });
    state.toggle('notes');
    expect(visible(state)).toEqual(['name', 'email', 'phone', 'notes']);
    expect(JSON.parse(window.localStorage.getItem(key)!)).toEqual([
        'name',
        'email',
        'phone',
        'notes',
    ]);
    state.toggle('email', false);
    state.toggle('phone', false);
    expect(visible(state)).toEqual(['name', 'notes']);
    state.toggle('phone', true);
    expect(visible(state)).toEqual(['name', 'phone', 'notes']);

    state.showAll();
    expect(visible(state)).toEqual(['name', 'email', 'phone', 'notes']);
    state.reset();
    expect(visible(state)).toEqual(['name', 'email', 'phone']);
    expect(JSON.parse(window.localStorage.getItem(key)!)).toEqual([
        'name',
        'email',
        'phone',
    ]);
});

it('never hides a required column and ignores unknown keys', () => {
    const state = useTableColumns({ listId: 'test.list', columns });
    state.toggle('name', false);
    state.toggle('secret', true);
    state.toggle('nope');
    expect(visible(state)).toEqual(['name', 'email', 'phone']);
    expect(window.localStorage.getItem(key)).toBeNull();

    window.localStorage.setItem(key, JSON.stringify(['phone']));
    expect(visible(useTableColumns({ listId: 'test.list', columns }))).toEqual([
        'name',
        'phone',
    ]);
});

it('keeps each store user and each list apart', () => {
    useTableColumns({ listId: 'test.list', columns }).toggle('email', false);
    expect(visible(useTableColumns({ listId: 'test.list', columns }))).toEqual([
        'name',
        'phone',
    ]);
    expect(visible(useTableColumns({ listId: 'test.other', columns }))).toEqual(
        ['name', 'email', 'phone'],
    );
    signedInAs(8);
    expect(visible(useTableColumns({ listId: 'test.list', columns }))).toEqual([
        'name',
        'email',
        'phone',
    ]);
    signedInAs(null);
    expect(visible(useTableColumns({ listId: 'test.list', columns }))).toEqual([
        'name',
        'email',
        'phone',
    ]);
});

it('reads a legacy key once, then keeps the choice under the new key', () => {
    window.localStorage.setItem('legacy.key', JSON.stringify(['email']));
    const state = useTableColumns({
        listId: 'test.list',
        columns,
        legacyStorageKey: 'legacy.key',
    });
    expect(visible(state)).toEqual(['name', 'email']);
    expect(JSON.parse(window.localStorage.getItem(key)!)).toEqual([
        'name',
        'email',
    ]);

    // The new key wins from then on.
    window.localStorage.setItem(key, JSON.stringify(['phone']));
    expect(
        visible(
            useTableColumns({
                listId: 'test.list',
                columns,
                legacyStorageKey: 'legacy.key',
            }),
        ),
    ).toEqual(['name', 'phone']);

    // A junk legacy value is no choice at all.
    window.localStorage.clear();
    window.localStorage.setItem('legacy.key', '{oops');
    expect(
        visible(
            useTableColumns({
                listId: 'test.list',
                columns,
                legacyStorageKey: 'legacy.key',
            }),
        ),
    ).toEqual(['name', 'email', 'phone']);
    expect(window.localStorage.getItem(key)).toBeNull();
});

it('follows a changing list id and a changing set of available columns', async () => {
    window.localStorage.setItem(
        tableColumnsStorageKey(7, 'test.seller'),
        JSON.stringify(['notes']),
    );
    const listId = ref('test.list');
    const withNotes = ref(true);
    const state = useTableColumns({
        listId,
        columns: () => [
            ...columns.filter((column) => column.key !== 'notes'),
            { key: 'notes', label: 'a.notes', available: withNotes.value },
        ],
    });
    expect(visible(state)).toEqual(['name', 'email', 'phone', 'notes']);

    listId.value = 'test.seller';
    await nextTick();
    expect(visible(state)).toEqual(['name', 'notes']);

    withNotes.value = false;
    await nextTick();
    expect(visible(state)).toEqual(['name']);
    expect(state.shown.value.notes).toBeUndefined();
});
