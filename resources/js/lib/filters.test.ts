import { describe, expect, it, vi } from 'vitest';
import { computed, nextTick, reactive, ref } from 'vue';
import { t } from '@/lib/i18n';
import {
    columnNumberOptions,
    countActive,
    createdDateRangeExclusivity,
    dateRangeActive,
    debounce,
    exclusivePair,
    selectedCountLabel,
    toggleSort,
    useColumnFilterPopover,
} from './filters';

describe('selectedCountLabel', () => {
    it('interpolates the given count into the translated label', () => {
        expect(selectedCountLabel(3)).toBe(
            t('cellLog.filters.selectedCount', { count: 3 }),
        );
    });
});

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

describe('dateRangeActive', () => {
    function filters(overrides: Partial<Record<string, string>> = {}) {
        return reactive({
            date_from: '',
            date_to: '',
            created_within_days: '',
            ...overrides,
        });
    }

    it('is false when no part of the window is set', () => {
        expect(dateRangeActive(filters())).toBe(false);
    });

    it('is true when only the from date is set', () => {
        expect(dateRangeActive(filters({ date_from: '2026-01-01' }))).toBe(
            true,
        );
    });

    it('is true when only the to date is set', () => {
        expect(dateRangeActive(filters({ date_to: '2026-01-01' }))).toBe(true);
    });

    it('is true when only the rolling day count is set', () => {
        expect(dateRangeActive(filters({ created_within_days: '7' }))).toBe(
            true,
        );
    });

    it('ignores unrelated filter fields', () => {
        const withNoise = reactive({
            date_from: '',
            date_to: '',
            created_within_days: '',
            expiration_date_from: '2026-01-01',
            product_id: ['3'],
        });

        expect(dateRangeActive(withNoise)).toBe(false);
    });

    it('tracks reactive changes to the window', () => {
        const f = filters();
        const active = computed(() => dateRangeActive(f));

        expect(active.value).toBe(false);

        f.created_within_days = '7';

        expect(active.value).toBe(true);
    });
});

describe('createdDateRangeExclusivity', () => {
    function filters() {
        return reactive({
            date_from: '',
            date_to: '',
            created_within_days: '',
        });
    }

    it('disables neither side when the whole window is empty', () => {
        const { dateRangeDisabled, createdWithinDaysDisabled } =
            createdDateRangeExclusivity(filters());

        expect(dateRangeDisabled.value).toBe(false);
        expect(createdWithinDaysDisabled.value).toBe(false);
    });

    it('disables the range fields once the day count is filled', () => {
        const f = filters();
        const { dateRangeDisabled, createdWithinDaysDisabled } =
            createdDateRangeExclusivity(f);

        f.created_within_days = '7';

        expect(dateRangeDisabled.value).toBe(true);
        expect(createdWithinDaysDisabled.value).toBe(false);
    });

    it('disables the day count once either range end is filled', () => {
        const fromOnly = filters();
        const fromPair = createdDateRangeExclusivity(fromOnly);
        fromOnly.date_from = '2026-01-01';

        const toOnly = filters();
        const toPair = createdDateRangeExclusivity(toOnly);
        toOnly.date_to = '2026-01-31';

        expect(fromPair.createdWithinDaysDisabled.value).toBe(true);
        expect(fromPair.dateRangeDisabled.value).toBe(false);
        expect(toPair.createdWithinDaysDisabled.value).toBe(true);
    });

    it('re-enables both sides once the filled field is cleared', () => {
        const f = filters();
        const { dateRangeDisabled, createdWithinDaysDisabled } =
            createdDateRangeExclusivity(f);

        f.created_within_days = '7';
        f.created_within_days = '';

        expect(dateRangeDisabled.value).toBe(false);
        expect(createdWithinDaysDisabled.value).toBe(false);
    });
});

describe('useColumnFilterPopover', () => {
    it('does not apply when a filter field changes and no popover is open', async () => {
        vi.useFakeTimers();
        const filters = reactive({ state: '' });
        const filtersOpen = ref(false);
        const applyFilters = vi.fn();
        useColumnFilterPopover(filters, filtersOpen, applyFilters);

        filters.state = 'full';
        await nextTick();
        vi.advanceTimersByTime(400);

        expect(applyFilters).not.toHaveBeenCalled();
        vi.useRealTimers();
    });

    it('debounces and applies once a popover is open and a filter field changes', async () => {
        vi.useFakeTimers();
        const filters = reactive({ state: '' });
        const filtersOpen = ref(false);
        const applyFilters = vi.fn();
        const { openFilterKey } = useColumnFilterPopover(
            filters,
            filtersOpen,
            applyFilters,
        );

        openFilterKey.value = 'state';
        filters.state = 'full';
        filters.state = 'opened';
        await nextTick();
        expect(applyFilters).not.toHaveBeenCalled();

        vi.advanceTimersByTime(400);

        expect(applyFilters).toHaveBeenCalledTimes(1);
        vi.useRealTimers();
    });

    it('does not apply while the full filter dialog is open', async () => {
        vi.useFakeTimers();
        const filters = reactive({ state: '' });
        const filtersOpen = ref(true);
        const applyFilters = vi.fn();
        const { openFilterKey } = useColumnFilterPopover(
            filters,
            filtersOpen,
            applyFilters,
        );

        openFilterKey.value = 'state';
        filters.state = 'full';
        await nextTick();
        vi.advanceTimersByTime(400);

        expect(applyFilters).not.toHaveBeenCalled();
        vi.useRealTimers();
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
