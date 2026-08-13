import { describe, expect, it, vi } from 'vitest';
import { applySortToggle, columnNumberOptions } from './filters';

describe('columnNumberOptions', () => {
    it('returns 1..maxColumnNumber', () => {
        expect(columnNumberOptions(3)).toEqual([1, 2, 3]);
    });

    it('returns an empty array when maxColumnNumber is 0', () => {
        expect(columnNumberOptions(0)).toEqual([]);
    });
});

describe('applySortToggle', () => {
    it('defaults to ascending when clicking a new column', () => {
        const filters = { sort_by: '', sort_direction: '' };
        const applyFilters = vi.fn();

        applySortToggle(filters, 'created_at', applyFilters);

        expect(filters.sort_by).toBe('created_at');
        expect(filters.sort_direction).toBe('asc');
        expect(applyFilters).toHaveBeenCalledOnce();
    });

    it('flips direction when re-clicking the active column', () => {
        const filters = { sort_by: 'created_at', sort_direction: 'asc' };
        const applyFilters = vi.fn();

        applySortToggle(filters, 'created_at', applyFilters);

        expect(filters.sort_by).toBe('created_at');
        expect(filters.sort_direction).toBe('desc');
    });

    it('resets to ascending when switching to a different column', () => {
        const filters = { sort_by: 'created_at', sort_direction: 'desc' };
        const applyFilters = vi.fn();

        applySortToggle(filters, 'expiration_date', applyFilters);

        expect(filters.sort_by).toBe('expiration_date');
        expect(filters.sort_direction).toBe('asc');
    });
});
