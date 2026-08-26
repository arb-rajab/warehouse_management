import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import {
    columnNumberOptions,
    countActive,
    debounce,
    exclusivePair,
    toggleSort,
} from './filters';

describe('columnNumberOptions', () => {
    it('returns 1..maxColumnNumber', () => {
        expect(columnNumberOptions(3)).toEqual([1, 2, 3]);
    });

    it('returns an empty array when maxColumnNumber is 0', () => {
        expect(columnNumberOptions(0)).toEqual([]);
    });
});

describe('countActive', () => {
    it('counts how many flags are true', () => {
        expect(countActive([true, false, true, true])).toBe(3);
    });

    it('returns 0 when no flags are true', () => {
        expect(countActive([false, false])).toBe(0);
    });

    it('returns 0 for an empty array', () => {
        expect(countActive([])).toBe(0);
    });
});

describe('toggleSort', () => {
    it('sorts a new column ascending', () => {
        const filters = { sort_by: '', sort_direction: '' };

        toggleSort(filters, 'created_at');

        expect(filters).toEqual({
            sort_by: 'created_at',
            sort_direction: 'asc',
        });
    });

    it('flips the direction when the same column is clicked again', () => {
        const filters = { sort_by: 'created_at', sort_direction: 'asc' };

        toggleSort(filters, 'created_at');

        expect(filters).toEqual({
            sort_by: 'created_at',
            sort_direction: 'desc',
        });
    });

    it('resets to ascending when switching to a different column', () => {
        const filters = { sort_by: 'created_at', sort_direction: 'desc' };

        toggleSort(filters, 'expiration_date');

        expect(filters).toEqual({
            sort_by: 'expiration_date',
            sort_direction: 'asc',
        });
    });
});

describe('exclusivePair', () => {
    it('disables neither side when both are empty', () => {
        const filters = reactive({ range: '', days: '' });
        const { rangeDisabled, daysDisabled } = exclusivePair(
            () => filters.range !== '',
            () => filters.days !== '',
        );

        expect(rangeDisabled.value).toBe(false);
        expect(daysDisabled.value).toBe(false);
    });

    it('disables the range fields once the days field is filled', () => {
        const filters = reactive({ range: '', days: '' });
        const { rangeDisabled, daysDisabled } = exclusivePair(
            () => filters.range !== '',
            () => filters.days !== '',
        );

        filters.days = '7';

        expect(rangeDisabled.value).toBe(true);
        expect(daysDisabled.value).toBe(false);
    });

    it('disables the days field once a range field is filled', () => {
        const filters = reactive({ range: '', days: '' });
        const { rangeDisabled, daysDisabled } = exclusivePair(
            () => filters.range !== '',
            () => filters.days !== '',
        );

        filters.range = '2026-01-01';

        expect(rangeDisabled.value).toBe(false);
        expect(daysDisabled.value).toBe(true);
    });

    it('re-enables both sides once the filled field is cleared', () => {
        const filters = reactive({ range: '', days: '' });
        const { rangeDisabled, daysDisabled } = exclusivePair(
            () => filters.range !== '',
            () => filters.days !== '',
        );

        filters.days = '7';
        filters.days = '';

        expect(rangeDisabled.value).toBe(false);
        expect(daysDisabled.value).toBe(false);
    });
});

describe('debounce', () => {
    it('calls the function once, after the delay, when called repeatedly in quick succession', () => {
        vi.useFakeTimers();
        const fn = vi.fn();
        const debounced = debounce(fn, 300);

        debounced('a');
        debounced('b');
        debounced('c');
        expect(fn).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);

        expect(fn).toHaveBeenCalledTimes(1);
        expect(fn).toHaveBeenCalledWith('c');
        vi.useRealTimers();
    });

    it('calls the function again for a call made after the delay has elapsed', () => {
        vi.useFakeTimers();
        const fn = vi.fn();
        const debounced = debounce(fn, 300);

        debounced('a');
        vi.advanceTimersByTime(300);
        debounced('b');
        vi.advanceTimersByTime(300);

        expect(fn).toHaveBeenCalledTimes(2);
        expect(fn).toHaveBeenNthCalledWith(1, 'a');
        expect(fn).toHaveBeenNthCalledWith(2, 'b');
        vi.useRealTimers();
    });
});
