import { describe, expect, it } from 'vitest';
import { formatDate, formatDateTime } from './date';

describe('formatDate', () => {
    it('formats a date-only string without a time component', () => {
        expect(formatDate('2026-08-20')).toBe('Aug 20, 2026');
    });
});

describe('formatDateTime', () => {
    it('formats an ISO timestamp with both date and time components', () => {
        const formatted = formatDateTime('2026-08-20T15:45:00Z');

        expect(formatted).toContain('Aug 20, 2026');
        expect(formatted).toMatch(/\d{1,2}:\d{2}/);
    });

    it('shares the same date formatting as formatDate', () => {
        const dateOnly = formatDate('2026-08-20');
        const dateTime = formatDateTime('2026-08-20T00:00:00');

        expect(dateTime.startsWith(dateOnly)).toBe(true);
    });
});
