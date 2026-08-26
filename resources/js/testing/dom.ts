import type { VueWrapper } from '@vue/test-utils';

/**
 * Shared "grab the cells of a table row" helper — every page test that
 * asserts on table row content built this expression independently before
 * this existed.
 */
export function rowCells(wrapper: VueWrapper, rowIndex = 0) {
    return wrapper.findAll('tbody tr')[rowIndex].findAll('td');
}
