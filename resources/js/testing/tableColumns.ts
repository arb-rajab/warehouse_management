import type { VueWrapper } from '@vue/test-utils';
import { expect } from 'vitest';
import { t } from '@/lib/i18n';
import { tableColumnsStorageKey } from '@/lib/useTableColumns';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';

/** The user id `defaultAuthProps()` signs in, which the storage key carries. */
const testUserId = (defaultAuthProps().auth as { user: { id: number } }).user
    .id;

/** The text of every header cell of the first table in the wrapper. */
export function headerTexts(wrapper: VueWrapper): string[] {
    return wrapper.findAll('thead th').map((th) => th.text());
}

/**
 * A table whose header and cells fell out of step (a column hidden in one but
 * not the other) has a different number of `th` and `td`; every row must match.
 */
export function expectHeadersMatchCells(wrapper: VueWrapper): void {
    const count = wrapper.findAll('thead th').length;
    const rows = wrapper.findAll('tbody tr');

    expect(rows.length).toBeGreaterThan(0);
    rows.forEach((row) => expect(row.findAll('td')).toHaveLength(count));
}

/** The column picker's dialog, found by its title (a page can hold several). */
export function columnsDialog(wrapper: VueWrapper) {
    return wrapper
        .findAll('[role="dialog"]')
        .find((item) => item.text().includes(t('store.table.columnsTitle')))!;
}

/** Click the "Columns" button and return the dialog it opens. */
export async function openColumnPicker(wrapper: VueWrapper) {
    await wrapper
        .findAll('button')
        .find((button) => button.text() === t('store.table.columns'))!
        .trigger('click');

    return columnsDialog(wrapper);
}

/** Tick or untick one column's checkbox (opening the dialog if needed). */
export async function setColumnShown(
    wrapper: VueWrapper,
    label: string,
    shown: boolean,
): Promise<void> {
    const dialog = columnsDialog(wrapper) ?? (await openColumnPicker(wrapper));

    await dialog
        .findAll('label')
        .find((item) => item.text() === label)!
        .get('input')
        .setValue(shown);
}

/** The checkbox labels the dialog offers, in order. */
export function offeredColumns(wrapper: VueWrapper): string[] {
    return columnsDialog(wrapper)
        .findAll('label')
        .map((item) => item.text());
}

/** The required column's checkbox is ticked and locked, and its header stays. */
export function expectColumnLocked(wrapper: VueWrapper, label: string): void {
    const box = columnsDialog(wrapper)
        .findAll('label')
        .find((item) => item.text() === label)!
        .get('input').element as HTMLInputElement;

    expect(box.checked).toBe(true);
    expect(box.disabled).toBe(true);
    expect(headerTexts(wrapper)).toContain(label);
}

/** What a store user (default: the test user) has stored for a list; `null` when nothing. */
export function storedColumns(
    listId: string,
    userId = testUserId,
): string[] | null {
    const raw = window.localStorage.getItem(
        tableColumnsStorageKey(userId, listId),
    );

    return raw === null ? null : (JSON.parse(raw) as string[]);
}

/** Remember a choice for a list as the test user (before mounting a page). */
export function storeColumns(
    listId: string,
    keys: string[],
    userId = testUserId,
): void {
    window.localStorage.setItem(
        tableColumnsStorageKey(userId, listId),
        JSON.stringify(keys),
    );
}

/**
 * The one assertion every picker page repeats: hiding `label` drops it from
 * the header and its cell from every row, the choice is remembered, and the
 * header and cells still match.
 */
export async function expectColumnCanBeHidden(
    wrapper: VueWrapper,
    listId: string,
    column: { key: string; label: string },
    userId = testUserId,
): Promise<void> {
    const before = headerTexts(wrapper);

    expect(before).toContain(column.label);
    expectHeadersMatchCells(wrapper);
    await openColumnPicker(wrapper);
    await setColumnShown(wrapper, column.label, false);

    const after = headerTexts(wrapper);

    expect(after).not.toContain(column.label);
    expect(after).toHaveLength(before.length - 1);
    expectHeadersMatchCells(wrapper);
    expect(storedColumns(listId, userId)).not.toContain(column.key);
}
